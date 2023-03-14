<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nafezly_payments', function (Blueprint $table) {
            $table->id();

            // maybe user, admin, driver, vendor etc.. (morph)
            $table->unsignedBigInteger('model_id');
            $table->string('model_table');

            // maybe order, service, subscription etc.. (morph)
            $table->unsignedBigInteger('order_id');
            $table->string('order_table');

            
            $table->string('payment_method');
            $table->string('payment_status');
            $table->string('transaction_code')->nullable()->unique();
            $table->double('amount')->unsigned()->default(0);
            $table->string('notes')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payments');
    }
};
