<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            if(!$table->hasColumn('status'))
                $table->string('status')->default("PENDING");
            if(!$table->hasColumn('payment_id'))
                $table->string('payment_id')->index()->nullable();
            if(!$table->hasColumn('amount'))
                $table->decimal('amount',8,3)->default(0.0);
            if(!$table->hasColumn('process_data'))
                $table->text('process_data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->dropColumn('payment_id');
            $table->dropColumn('amount');
            $table->dropColumn('process_data');
        });
    }
}