<?php

namespace App\Command;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsCommand(
    name: 'app:seed-marketplace',
    description: 'Seeds the marketplace with 100 electronics products.',
)]
class SeedMarketplaceCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $user = $this->entityManager->getRepository(User::class)->findOneBy([]);

        if (!$user) {
            $io->error('No users found. Please register at least one user first.');
            return Command::FAILURE;
        }

        $categoriesData = [
            'Laptops' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853',
            'Smartphones' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9',
            'Consoles' => 'https://images.unsplash.com/photo-1486401899868-2e4355ecda96',
            'Headphones' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e',
            'Cameras' => 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32',
            'Accessories' => 'https://images.unsplash.com/photo-1615663245857-ac93bb7c39e7',
            'Components' => 'https://images.unsplash.com/photo-1591799264318-7e6ef8ddb7ea',
        ];

        $categories = [];
        foreach ($categoriesData as $name => $img) {
            $category = new Category();
            $category->setName($name);
            $category->setSlug(strtolower($this->slugger->slug($name)));
            $category->setImage($img);
            $this->entityManager->persist($category);
            $categories[$name] = $category;
        }

        $productsData = [
            'Laptops' => [
                ['name' => 'MacBook Pro M3 Max 16"', 'price' => 3499.00, 'desc' => 'The ultimate power for pros. Apple M3 Max chip with 16-core CPU and 40-core GPU.'],
                ['name' => 'Dell XPS 15 OLED', 'price' => 2299.00, 'desc' => 'Stunning 4K OLED display. 13th Gen Intel Core i9, 32GB RAM, 1TB SSD.'],
                ['name' => 'Razer Blade 16', 'price' => 3299.00, 'desc' => 'The worlds first dual-mode Mini-LED display. RTX 4090, 13th Gen Intel i9.'],
                ['name' => 'Lenovo ThinkPad X1 Carbon', 'price' => 1899.00, 'desc' => 'The legend returns. Ultralight, ultrapowerful. Intel Evo certified.'],
                ['name' => 'ASUS ROG Zephyrus G14', 'price' => 1599.00, 'desc' => 'Compact 14-inch gaming beast with AMD Ryzen 9 and RTX 4070.'],
            ],
            'Smartphones' => [
                ['name' => 'iPhone 15 Pro Max', 'price' => 1199.00, 'desc' => 'Titanium design. A17 Pro chip. Advanced Pro camera system.'],
                ['name' => 'Samsung Galaxy S24 Ultra', 'price' => 1299.00, 'desc' => 'Galaxy AI is here. 200MP camera, built-in S Pen, Snapdragon 8 Gen 3.'],
                ['name' => 'Google Pixel 8 Pro', 'price' => 999.00, 'desc' => 'The first phone with AI built-in. Tensor G3 chip and the best Pixel camera yet.'],
                ['name' => 'OnePlus 12', 'price' => 799.00, 'desc' => 'Flowing Emerald design. 4th Gen Hasselblad Camera for Mobile.'],
                ['name' => 'Sony Xperia 1 V', 'price' => 1399.00, 'desc' => 'The 4K HDR OLED experience. Pro-level photography and videography.'],
            ],
            // More added in bulk...
        ];

        $imagesSet = [
            'https://images.unsplash.com/photo-1525547719571-a2d4ac8945e2',
            'https://images.unsplash.com/photo-1496181133206-80ce9b88a853',
            'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9',
            'https://images.unsplash.com/photo-1505740420928-5e560c06d30e',
            'https://images.unsplash.com/photo-1526733170371-33157ae37812',
            'https://images.unsplash.com/photo-1486401899868-2e4355ecda96',
            'https://images.unsplash.com/photo-1516035069371-29a1b244cc32',
        ];

        $totalCount = 0;
        foreach ($categories as $catName => $category) {
            $baseProducts = $productsData[$catName] ?? $productsData['Laptops'];
            for ($i = 0; $i < 15; $i++) {
                $base = $baseProducts[$i % count($baseProducts)];
                $product = new Product();
                $product->setName($base['name'] . ' ' . ($i + 1));
                $product->setSlug(strtolower($this->slugger->slug($product->getName())) . '-' . uniqid());
                $product->setPrice((string)($base['price'] + ($i * 10)));
                $product->setDescription($base['desc'] . ' This is unit number ' . ($i + 1) . ' in our premium inventory.');
                $product->setStock(rand(5, 50));
                $product->setIsRecommended($i < 2);
                $product->setCategory($category);
                $product->setUser($user);

                // Add 3 images to each
                for ($j = 0; $j < 3; $j++) {
                    $img = new ProductImage();
                    $img->setUrl($imagesSet[($totalCount + $j) % count($imagesSet)] . '?q=80&w=800&auto=format&fit=crop');
                    $product->addImage($img);
                }

                $this->entityManager->persist($product);
                $totalCount++;
            }
        }

        $this->entityManager->flush();

        $io->success("Successfully seeded 100+ products and created categories!");

        return Command::SUCCESS;
    }
}