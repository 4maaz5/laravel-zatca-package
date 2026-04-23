<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zatca_tenant_invoice_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('zatca_tenants')->cascadeOnDelete();
            $table->string('environment')->default('sandbox');
            $table->unsignedBigInteger('last_icv')->default(0);
            $table->longText('previous_invoice_hash')->nullable();
            $table->string('last_invoice_uuid')->nullable();
            $table->longText('last_invoice_hash')->nullable();
            $table->timestamp('last_submitted_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'environment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zatca_tenant_invoice_states');
    }
};
