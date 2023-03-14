<?php

namespace Nafezly\Payments\Seeders;

use Illuminate\Database\Seeder;
use Nafezly\Payments\Model\NafezlyPaymentMethod;

class PaymentMethodsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        $rows = [
            [
                'method' => 'Fawry',
                'name' => 'fawry Payment',
                'icon' => "bx bx-circle",
                'color' => "#b69ddd",
            ],[
                'method' => 'Paymob',
                'name' => 'pay by Paymob',
                'icon' => "bx bx-circle",
                'color' => "#ff0000",
            ],
            [
                'method' => 'PaymobWallet',
                'name' => 'pay by PaymobWallet',
                'icon' => "bx bx-circle",
                'color' => "#ff0000",
            ], [
                'method' => 'Paytabs',
                'name' => 'pay by Paytabs',
                'icon' => "bx bx-circle",
                'color' => "#ff0000",
            ],
            [
                'method' => 'HyperPay',
                'name' => 'pay by HyperPay',
                'icon' => "bx bx-circle",
                'color' => "#ff0000",
            ],
            [
                'method' => 'Kashier',
                'name' => 'pay by Kashier',
                'icon' => "bx bx-circle",
                'color' => "#ff0000",
            ]   ,
            [
                'method'=>'PayPal',
                'name' => 'pay by PayPal',
                'icon'=>"bx bx-circle",
                'color'=>"#ff0000",
            ] ,
            [
                'method'=>'Thawani',
                'name' => 'pay by Thawani',
                'icon'=>"bx bx-circle",
                'color'=>"#ff0000",
            ],
            [
                'method'=>'Tap',
                'name' => 'pay by Tap',
                'icon'=>"bx bx-circle",
                'color'=>"#ff0000",
            ],[
                'method'=>'Opay',
                'name' => 'pay by Opay',
                'icon'=>"bx bx-circle",
                'color'=>"#ff0000",
            ]
        ];

        foreach ($rows as $row){
            $method=NafezlyPaymentMethod::where('method',$row['method']);
            if($method->count()){
                $method->update($row);
            }else
                NafezlyPaymentMethod::create($row);
        }
    }
}
