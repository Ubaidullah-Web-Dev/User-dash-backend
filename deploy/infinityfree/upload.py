#!/usr/bin/env python3
"""Uploads the output of build.sh to InfinityFree over FTP (or FTPS when the server offers it).

    python3 deploy/infinityfree/upload.py [--env FILE] [--site DIR] [--delete] [--dry-run]

Reads FTP_HOST / FTP_USER / FTP_PASSWORD from prod.env and mirrors the CONTENTS of the build's htdocs/
into the remote htdocs/. Resumable: files already on the server with the same size are skipped, so after a
dropped connection just run it again. Files under var/cache/ are always re-sent (a new build may reuse a name).

--delete  also removes remote files that are not in the build (old cache files, removed code). It never
          touches var/log, var/sessions or public/uploads.
"""
import argparse
import ftplib
import os
import posixpath
import sys
import threading
import time
from concurrent.futures import ThreadPoolExecutor

HERE = os.path.dirname(os.path.abspath(__file__))
BACKEND = os.path.dirname(os.path.dirname(HERE))
PROTECTED = ('var/log', 'var/sessions', 'public/uploads')
ALWAYS_SEND = ('var/cache/', '.env.local.php', 'public/assets/manifest.json', 'public/assets/importmap.json')


def read_env(path):
    values = {}
    with open(path, encoding='utf-8') as fh:
        for line in fh:
            if '=' in line and not line.lstrip().startswith('#'):
                key, value = line.rstrip('\n').split('=', 1)
                values[key.strip()] = value.strip().strip('"').strip("'")
    return values


class Remote:
    def __init__(self, host, port, user, password):
        self.args = (host, port, user, password)
        self.listing = {}
        self.ftp_announced = False
        self.connect()

    def connect(self):
        host, port, user, password = self.args
        ftp = ftplib.FTP_TLS(timeout=60)
        ftp.connect(host, port)
        try:
            ftp.auth()  # explicit FTPS, so the password is not sent in clear text
            encrypted = True
        except (ftplib.error_perm, OSError):
            ftp.close()
            ftp = ftplib.FTP(timeout=60)
            ftp.connect(host, port)
            encrypted = False
        ftp.login(user, password)
        if encrypted:
            ftp.prot_p()
        if not self.ftp_announced:
            print('Connected with FTPS (encrypted).' if encrypted else 'Connected with plain FTP (server does not offer FTPS).')
            self.ftp_announced = True
        ftp.set_pasv(True)
        self.ftp = ftp

    def call(self, fn, *args):
        """Runs an FTP command, reconnecting up to 5 times on dropped connections."""
        for attempt in range(6):
            try:
                return fn(self.ftp, *args)
            except (ftplib.error_temp, OSError, EOFError) as exc:
                if attempt == 5:
                    raise
                print(f'  connection problem ({exc}); reconnecting...')
                time.sleep(3 * (attempt + 1))
                try:
                    self.ftp.close()
                except Exception:
                    pass
                self.connect()

    def list_dir(self, path):
        """{name: (type, size)} for a remote directory, or None if it does not exist."""
        if path in self.listing:
            return self.listing[path]

        def run(ftp, p):
            try:
                entries = {}
                for name, facts in ftp.mlsd(p, facts=['type', 'size']):
                    if name not in ('.', '..'):
                        entries[name] = (facts.get('type'), int(facts.get('size', -1) or -1))
                return entries
            except ftplib.error_perm as exc:
                if str(exc).startswith('550'):
                    return None
                raise
        self.listing[path] = self.call(run, path)
        return self.listing[path]

    def mkdir(self, path):
        parent, name = posixpath.split(path)
        entries = self.list_dir(parent)
        if entries is not None and name in entries:
            return
        self.call(lambda ftp, p: ftp.mkd(p), path)
        if entries is not None:
            entries[name] = ('dir', -1)
        self.listing[path] = {}

    def upload(self, local, remote):
        def run(ftp, l, r):
            with open(l, 'rb') as fh:
                ftp.storbinary(f'STOR {r}', fh, blocksize=64 * 1024)
        self.call(run, local, remote)

    def delete(self, path, is_dir):
        self.call(lambda ftp, p: ftp.rmd(p) if is_dir else ftp.delete(p), path)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--env', default=os.path.join(HERE, 'prod.env'))
    parser.add_argument('--site', default=os.path.join(BACKEND, 'var', 'deploy', 'infinityfree', 'htdocs'))
    parser.add_argument('--remote-root', help='remote website folder (overrides FTP_ROOT in prod.env)')
    parser.add_argument('--delete', action='store_true')
    parser.add_argument('--dry-run', action='store_true')
    parser.add_argument('--workers', type=int, default=6, help='parallel FTP connections (default 6)')
    args = parser.parse_args()

    env = read_env(args.env)
    for key in ('FTP_HOST', 'FTP_USER', 'FTP_PASSWORD'):
        if not env.get(key) or '00000000' in env[key] or env[key] == 'change-me':
            sys.exit(f'Set {key} in {args.env} first.')
    if not os.path.isfile(os.path.join(args.site, '.env.local.php')):
        sys.exit(f'No build found in {args.site}. Run deploy/infinityfree/build.sh first.')

    root = (args.remote_root or env.get('FTP_ROOT') or '/htdocs').rstrip('/')
    if not root.startswith('/') or root == '' or '..' in root.split('/'):
        sys.exit('Remote root must be an absolute website folder, e.g. /apps.example.com/htdocs.')
    remote = Remote(env['FTP_HOST'], int(env.get('FTP_PORT') or 21), env['FTP_USER'], env['FTP_PASSWORD'])
    if remote.list_dir(root) is None:
        sys.exit(f'Remote folder {root} not found. Check the FTP account (it should contain htdocs/).')

    local_files = []
    for dirpath, dirnames, filenames in os.walk(args.site):
        dirnames.sort()
        for name in sorted(filenames):
            full = os.path.join(dirpath, name)
            local_files.append(os.path.relpath(full, args.site).replace(os.sep, '/'))

    # Phase 0: read the existing remote folders in parallel (one round trip each, slow on free hosting).
    local = threading.local()
    needed_dirs = sorted({posixpath.dirname(posixpath.join(root, rel)) for rel in local_files})

    def fetch_listing(path):
        if not hasattr(local, 'remote'):
            local.remote = Remote(*remote.args)
        local.remote.listing = remote.listing  # shared cache (dict writes are atomic)
        local.remote.list_dir(path)

    with ThreadPoolExecutor(max_workers=args.workers) as pool:
        list(pool.map(fetch_listing, needed_dirs))

    # Create parent directories before their children, with independent folders in parallel.
    # A fresh custom-domain deployment otherwise spends most of its time on serial MKD calls.
    if not args.dry_run:
        all_dirs = set(needed_dirs)
        for path in needed_dirs:
            parent = posixpath.dirname(path)
            while parent != root and parent.startswith(root + '/'):
                all_dirs.add(parent)
                parent = posixpath.dirname(parent)
        levels = {}
        for path in all_dirs - {root}:
            depth = posixpath.relpath(path, root).count('/')
            levels.setdefault(depth, []).append(path)

        def ensure_dir(path):
            if not hasattr(local, 'remote'):
                local.remote = Remote(*remote.args)
            local.remote.listing = remote.listing
            local.remote.mkdir(path)

        print(f'Preparing {len(all_dirs)} website folders.', flush=True)
        with ThreadPoolExecutor(max_workers=args.workers) as pool:
            for depth in sorted(levels):
                list(pool.map(ensure_dir, sorted(levels[depth])))
                print(f'  folder depth {depth + 1} ready', flush=True)

    # Phase 1: work out which files need sending.
    to_send, skipped = [], 0
    for rel in local_files:
        full = os.path.join(args.site, rel)
        rdir, name = posixpath.split(posixpath.join(root, rel))
        existing = remote.list_dir(rdir) or {}
        if name in existing and existing[name][1] == os.path.getsize(full) and not rel.startswith(ALWAYS_SEND):
            skipped += 1
        else:
            to_send.append(rel)
    print(f'{len(to_send)} files to upload, {skipped} already up to date. Using {args.workers} connections.')

    # Phase 2: upload over several connections in parallel (each FTP command waits on a round trip).
    lock = threading.Lock()
    progress = {'sent': 0}
    started = time.time()

    def send(rel):
        if args.dry_run:
            return
        if not hasattr(local, 'remote'):
            local.remote = Remote(*remote.args)
        local.remote.upload(os.path.join(args.site, rel), posixpath.join(root, rel))
        with lock:
            progress['sent'] += 1
            done = progress['sent']
        if done % 100 == 0 or done == len(to_send):
            rate = done / max(time.time() - started, 1)
            left = (len(to_send) - done) / max(rate, 0.01)
            print(f'  {done}/{len(to_send)} uploaded ({rate:.1f} files/s, ~{left / 60:.0f} min left) - {rel}', flush=True)

    with ThreadPoolExecutor(max_workers=args.workers) as pool:
        for future in [pool.submit(send, rel) for rel in to_send]:
            future.result()
    sent = len(to_send)

    deleted = 0
    if args.delete:
        wanted = set(local_files)
        wanted_dirs = {posixpath.dirname(f) for f in local_files}
        for d in list(wanted_dirs):
            while d:
                d = posixpath.dirname(d)
                wanted_dirs.add(d)

        def prune(rel_dir):
            nonlocal deleted
            entries = remote.list_dir(posixpath.join(root, rel_dir) if rel_dir else root) or {}
            for name, (kind, _) in sorted(entries.items()):
                rel = posixpath.join(rel_dir, name) if rel_dir else name
                if rel.startswith(PROTECTED) or rel in PROTECTED:
                    continue
                if kind == 'dir':
                    prune(rel)
                    if rel not in wanted_dirs:
                        print(f'  delete dir  {rel}')
                        if not args.dry_run:
                            remote.delete(posixpath.join(root, rel), True)
                elif rel not in wanted:
                    print(f'  delete file {rel}')
                    deleted += 1
                    if not args.dry_run:
                        remote.delete(posixpath.join(root, rel), False)
        prune('')

    print(f'Done: {sent} uploaded, {skipped} unchanged, {deleted} deleted' + (' (dry run)' if args.dry_run else '') + '.')


if __name__ == '__main__':
    main()
