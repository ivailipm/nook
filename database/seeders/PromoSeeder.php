<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class PromoSeeder extends Seeder
{
    /**
     * Seed the prototype's sample promos for the user, unless they already have promos,
     * then link the user's items to them the way the prototype does.
     */
    public function run(User $user): void
    {
        if ($user->promos()->exists()) {
            return;
        }

        $day = fn (int $offset) => today()->addDays($offset);

        $promos = $user->promos()->createMany([
            ['store' => 'Colruyt', 'product' => 'Coffee beans 1 kg', 'deal' => '2+1 free', 'note' => '€11.98 for three', 'valid_from' => $day(-2), 'valid_until' => $day(2), 'tone' => 0],
            ['store' => 'Delhaize', 'product' => 'Dish soap', 'deal' => '−30%', 'note' => '€2.09 instead of €2.99', 'valid_from' => $day(-5), 'valid_until' => $day(1), 'tone' => 1],
            ['store' => 'Lidl', 'product' => 'Greek yoghurt', 'deal' => '2nd ½ price', 'note' => null, 'valid_from' => $day(-1), 'valid_until' => $day(5), 'tone' => 2],
            ['store' => 'Aldi', 'product' => 'Pasta 1 kg', 'deal' => '€1.79', 'note' => 'instead of €2.39', 'valid_from' => $day(0), 'valid_until' => $day(6), 'tone' => 3],
            ['store' => 'Colruyt', 'product' => 'Laundry pods', 'deal' => '−25%', 'note' => null, 'valid_from' => $day(-3), 'valid_until' => $day(12), 'tone' => 4],
            ['store' => 'Lidl', 'product' => 'Avocados ×4', 'deal' => '€2.49', 'note' => null, 'valid_from' => $day(-1), 'valid_until' => $day(2), 'tone' => 5],
            ['store' => 'Aldi', 'product' => 'Mandarins 2 kg', 'deal' => '1+1', 'note' => null, 'valid_from' => $day(-8), 'valid_until' => $day(-2), 'tone' => 3],
            ['store' => 'Delhaize', 'product' => 'Kitchen roll', 'deal' => '−40%', 'note' => null, 'valid_from' => $day(-10), 'valid_until' => $day(-4), 'tone' => 1],
            ['store' => 'Lidl', 'product' => 'Gouda slices', 'deal' => '€1.99', 'note' => null, 'valid_from' => $day(-7), 'valid_until' => $day(-1), 'tone' => 2],
        ]);

        // Coffee beans, Dish soap, Greek yoghurt and Laundry pods are on p1, p2, p3 and p5. Missing items are skipped.
        foreach (['Coffee beans' => 0, 'Dish soap' => 1, 'Greek yoghurt' => 2, 'Laundry pods' => 4] as $name => $index) {
            $user->groceryItems()->where('name', $name)->update(['promo_id' => $promos[$index]->id]);
        }
    }
}
