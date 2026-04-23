<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zatca_tenant_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('zatca_tenants')->cascadeOnDelete();
            $table->string('environment')->default('sandbox');
            $table->string('invoice_number')->nullable();
            $table->uuid('uuid')->nullable()->index();
            $table->string('mode')->default('reporting');
            $table->string('invoice_type')->nullable();
            $table->string('status')->default('draft');
            $table->string('reporting_status')->nullable();
            $table->string('clearance_status')->nullable();
            $table->string('invoice_hash')->nullable();
            $table->longText('qr_code')->nullable();
            $table->json('seller')->nullable();
            $table->json('buyer')->nullable();
            $table->json('items')->nullable();
            $table->json('invoice_payload')->nullable();
            $table->json('api_response')->nullable();
            $table->longText('xml')->nullable();
            $table->longText('signed_xml')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'environment', 'mode']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zatca_tenant_invoices');
    }
};
