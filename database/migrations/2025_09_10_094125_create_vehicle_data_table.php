<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vehicle_data', function (Blueprint $table) {
            $table->id();
            
            // Declarant ma'lumotlari
            $table->string('order_number')->nullable()->comment('Tartib raqami');
            $table->string('queue_type')->nullable()->comment('Navbat turi');
            $table->string('reg_number')->unique()->comment('Mashina raqami');
            $table->string('registration_date')->nullable()->comment('Ro\'yxatga olingan sana');
            $table->string('status_changed')->nullable()->comment('Holat o\'zgargan vaqt');
            $table->string('declarant_status')->nullable()->comment('Declarant holati');
            $table->string('region')->nullable()->comment('Hudud');
            
            // Mintrans ma'lumotlari
            $table->string('rusumi')->nullable()->comment('Transport rusumi');
            $table->string('yuk_qobiliyati')->nullable()->comment('Yuk ko\'tarish qobiliyati');
            $table->string('license')->nullable()->comment('Litsenziya varaqasi');
            $table->string('state_number')->nullable()->comment('Davlat raqami');
            $table->text('company')->nullable()->comment('Korxona nomi');
            $table->string('phone_number')->nullable()->comment('Telefon raqami');
            $table->string('activity_type')->nullable()->comment('Faoliyat turi');
            $table->string('transport_type')->nullable()->comment('Transport turi');
            $table->string('cargo_type')->nullable()->comment('Yuk turi');
            $table->string('issue_date')->nullable()->comment('Berilgan sana');
            $table->string('expiry_date')->nullable()->comment('Amal qilish muddati');
            $table->string('mintrans_status')->nullable()->comment('Mintrans holati');
            $table->string('regional_office')->nullable()->comment('Hududiy boshqarma');
            
            $table->timestamps();
            
            // Indekslar
            $table->index('reg_number');
            $table->index('region');
            $table->index('declarant_status');
            $table->index('mintrans_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_data');
    }
};