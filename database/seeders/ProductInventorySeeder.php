<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\InventoryMovement;
use App\Models\MenuIngredient;
use App\Models\Product;
use App\Models\StockTake;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductInventorySeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $branches = Branch::all();
        $users = User::all();

        $managerUsers = $users->filter(fn ($u) => $u->role === 'manajer');
        $chefUsers = $users->filter(fn ($u) => $u->role === 'chef');

        $ingredients = [];

        foreach ($branches as $branch) {
            $branchIngredients = [
                [
                    'name' => 'Beras',
                    'current_stock' => 50000,
                    'unit' => 'gram',
                    'price_per_unit' => 150,
                ],
                [
                    'name' => 'Minyak Goreng',
                    'current_stock' => 5000,
                    'unit' => 'ml',
                    'price_per_unit' => 25,
                ],
                [
                    'name' => 'Garam',
                    'current_stock' => 1000,
                    'unit' => 'gram',
                    'price_per_unit' => 30,
                ],
                [
                    'name' => 'Bawang Putih',
                    'current_stock' => 500,
                    'unit' => 'gram',
                    'price_per_unit' => 50,
                ],
                [
                    'name' => 'Bawang Bombay',
                    'current_stock' => 800,
                    'unit' => 'gram',
                    'price_per_unit' => 35,
                ],
                [
                    'name' => 'Cabe Rawit',
                    'current_stock' => 300,
                    'unit' => 'gram',
                    'price_per_unit' => 60,
                ],
                [
                    'name' => 'Tomat',
                    'current_stock' => 1000,
                    'unit' => 'gram',
                    'price_per_unit' => 25,
                ],
                [
                    'name' => 'Ayam kampung',
                    'current_stock' => 5000,
                    'unit' => 'gram',
                    'price_per_unit' => 450,
                ],
                [
                    'name' => 'Ayam Broiler',
                    'current_stock' => 8000,
                    'unit' => 'gram',
                    'price_per_unit' => 350,
                ],
                [
                    'name' => 'Sapi',
                    'current_stock' => 3000,
                    'unit' => 'gram',
                    'price_per_unit' => 750,
                ],
                [
                    'name' => 'Ikan Salmon',
                    'current_stock' => 2000,
                    'unit' => 'gram',
                    'price_per_unit' => 800,
                ],
                [
                    'name' => 'Udang Vaname',
                    'current_stock' => 1500,
                    'unit' => 'gram',
                    'price_per_unit' => 650,
                ],
                [
                    'name' => 'Tahu',
                    'current_stock' => 2000,
                    'unit' => 'gram',
                    'price_per_unit' => 20,
                ],
                [
                    'name' => 'Tempe',
                    'current_stock' => 1500,
                    'unit' => 'gram',
                    'price_per_unit' => 25,
                ],
                [
                    'name' => 'Kecap Manis',
                    'current_stock' => 1000,
                    'unit' => 'ml',
                    'price_per_unit' => 18,
                ],
                [
                    'name' => 'Saus Tomat',
                    'current_stock' => 800,
                    'unit' => 'ml',
                    'price_per_unit' => 22,
                ],
                [
                    'name' => 'Saus Sambal',
                    'current_stock' => 600,
                    'unit' => 'ml',
                    'price_per_unit' => 20,
                ],
                [
                    'name' => 'Mentega',
                    'current_stock' => 500,
                    'unit' => 'gram',
                    'price_per_unit' => 85,
                ],
                [
                    'name' => 'Keju',
                    'current_stock' => 600,
                    'unit' => 'gram',
                    'price_per_unit' => 150,
                ],
                [
                    'name' => 'Cream',
                    'current_stock' => 400,
                    'unit' => 'ml',
                    'price_per_unit' => 45,
                ],
                [
                    'name' => 'Susu',
                    'current_stock' => 2000,
                    'unit' => 'ml',
                    'price_per_unit' => 15,
                ],
                [
                    'name' => 'Tepung Terigu',
                    'current_stock' => 5000,
                    'unit' => 'gram',
                    'price_per_unit' => 12,
                ],
                [
                    'name' => 'Tepung Maizena',
                    'current_stock' => 1000,
                    'unit' => 'gram',
                    'price_per_unit' => 18,
                ],
                [
                    'name' => 'Telur Ayam',
                    'current_stock' => 120,
                    'unit' => 'butir',
                    'price_per_unit' => 8,
                ],
                [
                    'name' => 'Jeruk Nipis',
                    'current_stock' => 500,
                    'unit' => 'gram',
                    'price_per_unit' => 40,
                ],
                [
                    'name' => 'Daun Bawang',
                    'current_stock' => 300,
                    'unit' => 'gram',
                    'price_per_unit' => 25,
                ],
                [
                    'name' => 'Seledri',
                    'current_stock' => 200,
                    'unit' => 'gram',
                    'price_per_unit' => 30,
                ],
                [
                    'name' => 'Kemangi',
                    'current_stock' => 150,
                    'unit' => 'gram',
                    'price_per_unit' => 35,
                ],
                [
                    'name' => 'Gula Pasir',
                    'current_stock' => 2000,
                    'unit' => 'gram',
                    'price_per_unit' => 18,
                ],
                [
                    'name' => 'Gula Merah',
                    'current_stock' => 1000,
                    'unit' => 'gram',
                    'price_per_unit' => 22,
                ],
            ];

            foreach ($branchIngredients as $ingredientData) {
                $ingredient = Ingredient::create([
                    'branch_id' => $branch->id,
                    'name' => $ingredientData['name'],
                    'current_stock' => $ingredientData['current_stock'],
                    'unit' => $ingredientData['unit'],
                    'price_per_unit' => $ingredientData['price_per_unit'],
                ]);
                $ingredients[$branch->id][] = $ingredient;
            }
        }

        $products = [];

        foreach ($branches as $branch) {
            $branchProducts = [
                [
                    'name' => 'Nasi Putih',
                    'price' => 5000,
                ],
                [
                    'name' => 'Nasi Goreng Special',
                    'price' => 35000,
                ],
                [
                    'name' => 'Nasi Goreng Mawut',
                    'price' => 40000,
                ],
                [
                    'name' => 'Ayam Goreng Kalasan',
                    'price' => 28000,
                ],
                [
                    'name' => 'Ayam Bakar',
                    'price' => 32000,
                ],
                [
                    'name' => 'Sapi Ladeh',
                    'price' => 45000,
                ],
                [
                    'name' => 'Ikan Salmon Grill',
                    'price' => 65000,
                ],
                [
                    'name' => 'Udang Goreng Tepung',
                    'price' => 55000,
                ],
                [
                    'name' => 'Tahu Goreng',
                    'price' => 15000,
                ],
                [
                    'name' => 'Tempe Goreng',
                    'price' => 15000,
                ],
                [
                    'name' => 'Sayur Asem',
                    'price' => 18000,
                ],
                [
                    'name' => 'Sayur Lodeh',
                    'price' => 20000,
                ],
                [
                    'name' => 'Soto Ayam',
                    'price' => 25000,
                ],
                [
                    'name' => 'Soto Betawi',
                    'price' => 30000,
                ],
                [
                    'name' => 'Bakso',
                    'price' => 22000,
                ],
                [
                    'name' => 'Mie Goreng',
                    'price' => 25000,
                ],
                [
                    'name' => 'Mie Rebus',
                    'price' => 25000,
                ],
                [
                    'name' => 'Kwetiau Goreng',
                    'price' => 30000,
                ],
                [
                    'name' => 'Bihun Goreng',
                    'price' => 28000,
                ],
                [
                    'name' => 'Es Teh Manis',
                    'price' => 5000,
                ],
                [
                    'name' => 'Es Jeruk',
                    'price' => 8000,
                ],
                [
                    'name' => 'Kopi Hitam',
                    'price' => 10000,
                ],
                [
                    'name' => 'Kopi Susu',
                    'price' => 15000,
                ],
                [
                    'name' => 'Jus Apel',
                    'price' => 18000,
                ],
                [
                    'name' => 'Jus Jeruk',
                    'price' => 15000,
                ],
            ];

            foreach ($branchProducts as $productData) {
                $product = Product::create([
                    'branch_id' => $branch->id,
                    'name' => $productData['name'],
                    'price' => $productData['price'],
                ]);
                $products[$branch->id][] = $product;
            }
        }

        foreach ($branches as $branch) {
            foreach ($products[$branch->id] as $product) {
                $branchIngredientsList = $ingredients[$branch->id];
                $numIngredients = rand(2, 5);
                $shuffled = collect($branchIngredientsList)->shuffle()->take($numIngredients);

                foreach ($shuffled as $ingredient) {
                    $quantityUsed = rand(50, 500);
                    MenuIngredient::create([
                        'product_id' => $product->id,
                        'ingredient_id' => $ingredient->id,
                        'quantity_used' => $quantityUsed,
                    ]);
                }
            }
        }

        foreach ($branches as $branch) {
            $branchIngredientsList = $ingredients[$branch->id];

            foreach ($branchIngredientsList as $ingredient) {
                InventoryMovement::create([
                    'branch_id' => $branch->id,
                    'ingredient_id' => $ingredient->id,
                    'type' => 'purchase',
                    'quantity' => rand(1000, 10000),
                    'reference_id' => 'PO-'.strtoupper(uniqid()),
                    'price_per_unit' => $ingredient->price_per_unit,
                ]);

                InventoryMovement::create([
                    'branch_id' => $branch->id,
                    'ingredient_id' => $ingredient->id,
                    'type' => 'usage',
                    'quantity' => rand(100, 1000),
                    'reference_id' => 'ORD-'.rand(1000, 9999),
                    'price_per_unit' => null,
                ]);
            }
        }

        foreach ($branches as $branch) {
            $branchIngredientsList = $ingredients[$branch->id];
            $selectedIngredients = collect($branchIngredientsList)->shuffle()->take(5);

            foreach ($selectedIngredients as $ingredient) {
                $systemQty = $ingredient->current_stock;
                $physicalQty = $systemQty + rand(-200, 200);
                $difference = $physicalQty - $systemQty;

                StockTake::create([
                    'ingredient_id' => $ingredient->id,
                    'user_id' => $managerUsers->random()->id,
                    'system_qty' => $systemQty,
                    'physical_qty' => $physicalQty,
                    'difference' => $difference,
                    'notes' => $difference === 0 ? 'Stock cocok' : 'Selisih '.abs($difference),
                ]);
            }
        }
    }
}
