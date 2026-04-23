<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zatca_tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('legal_name');
            $table->string('legal_name_ar')->nullable();
            $table->string('seller_name')->nullable();
            $table->string('seller_name_ar')->nullable();
            $table->string('vat_number', 15)->unique();
            $table->string('crn')->nullable();
            $table->string('branch_name')->nullable();
            $table->string('branch_name_ar')->nullable();
            $table->string('country_code', 2)->default('SA');
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('street')->nullable();
            $table->string('building_number')->nullable();
            $table->string('additional_number')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('locale', 5)->default('en');
            $table->string('timezone')->default('Asia/Riyadh');
            $table->string('default_environment')->default('sandbox');
            $table->string('onboarding_status')->default('draft');
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zatca_tenants');
    }
};
