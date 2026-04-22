# Backend API - Multi-Tenant Core Architecture

A highly robust and secure PHP/Symfony backend serving as the operational foundation for the Next.js multi-tenant frontend. It seamlessly handles intricate business logic, data modeling, e-commerce transactions, and versatile multi-role authorization schemes.

## 🚀 Key Features

| Feature | Description |
| ------- | ----------- |
| **RESTful JSON API** | Clean and localized API endpoint structure delivering data to the frontend counterparts. |
| **Multi-Tenancy** | Logic structure strongly tied to multiple companies (tenants) facilitating complete data segregation. |
| **Role-Based Access Control** | Secure routes based on diverse roles: `ROLE_SUPER_ADMIN`, `ROLE_ADMIN`, `ROLE_LAB_ADMIN`, `ROLE_VENDOR`, `ROLE_USER`. |
| **JWT Authentication** | Stateless and strongly secure endpoints facilitated by `LexikJWTAuthenticationBundle`. |
| **Complex Order Management** | Management of User/Vendor Carts, Orders, Stocks, and individual Lab Expenses tracked by associated tenants. |
| **PDF Rendering** | Instant internal generation of clean Lab Invoices and user Receipts using the `Dompdf` library integration. |
| **Mailer Integrations** | Reliable SMTP background emailing via Symfony Mailer accommodating Password Resets and critical Notifications. |

## 🛠️ Technology Stack

| Technology | Purpose |
| ---------- | ------- |
| **PHP 8.1+** | Highly performant core and primary object-oriented development language. |
| **Symfony 6.4** | Enterprise-grade PHP framework orchestrating the complete project infrastructure. |
| **Doctrine ORM** | Reliable Object-Relational Mapping (ORM) used for schema definitions and queries. |
| **MySQL / MariaDB** | Production proven relational database architecture. |
| **LexikJWTAuthentication**| Robust framework for provisioning, validating, and interacting with JSON Web Tokens. |
| **Dompdf** | HTML to PDF rendering library for documentation generation. |

## 💻 Local Setup & Installation

### Prerequisites

- **PHP 8.1** or higher configured with necessary extensions (`pdo_mysql`, `gd`, `zip`, etc.)
- **Composer** (PHP Package/Dependency Manager)
- **MySQL / MariaDB Server** (Can be executed via native installation, Docker, XAMPP, etc.)
- **Symfony CLI** (Optional, but highly recommended for launching the dev server)

### Installation Steps

1. **Clone the repository** and navigate to the backend directory:
   ```bash
   cd backend
   ```

2. **Install Composer Dependencies**:
   ```bash
   composer install
   ```

3. **Configure Environment Variables**:
   Modify the `.env` (or copy as `.env.local`) file inserting the relevant database credentials and mailer DSN:
   ```env
   # Database Configuration (ensure version reflects your local setup)
   DATABASE_URL="mysql://db_username:db_password@127.0.0.1:3306/db_name?serverVersion=mariadb-10.6.14"

   # Mailer Configuration for notifications (Example using Gmail SMTP)
   MAILER_DSN=smtp://your-email@gmail.com:your-app-password@smtp.gmail.com:587
   ```

4. **Generate JWT RSA Keys**:
   The backend's authentication fully relies on public/private RSA keys for JWT mapping. Generate them executing:
   ```bash
   php bin/console lexik:jwt:generate-keypair
   ```

5. **Ensure Database & Schemas are Constructed**:
   Initialize the database wrapper and deploy tables using your migrations:
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```

6. **Start the Local Development Server**:
   Using the Symfony CLI tool:
   ```bash
   symfony server:start
   ```
   *(By default, this server mounts locally to `http://127.0.0.1:8000`)*

## 📂 Core Architecture Folders

- `src/Controller/` - HTTP Request Managers/Routers (`AdminController`, `AuthController`, `LabAdminController`, `CartController`, etc.)
- `src/Entity/` - Core entity definitions & properties (`Company`, `User`, `LabExpense`, `Product`, `VendorOrder`) mapped to the database.
- `src/Repository/` - Custom and pre-defined queries interfacing exclusively with database tables.
- `config/` - Global Application Definitions (incorporates specific packages, configurations, bundles, and YAML directives).
