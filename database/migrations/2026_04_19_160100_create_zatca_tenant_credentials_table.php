<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zatca_tenant_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('zatca_tenants')->cascadeOnDelete();
            $table->string('environment')->default('sandbox');
            $table->string('signer')->default('sdk');
            $table->string('status')->default('draft');
            $table->string('compliance_request_id')->nullable();
            $table->longText('csr_base64')->nullable();
            $table->longText('csr_pem')->nullable();
            $table->longText('private_key')->nullable();
            $table->text('private_key_secret')->nullable();
            $table->longText('compliance_binary_security_token')->nullable();
            $table->text('compliance_secret')->nullable();
            $table->longText('production_binary_security_token')->nullable();
            $table->text('production_secret')->nullable();
            $table->timestamp('compliance_issued_at')->nullable();
            $table->timestamp('production_issued_at')->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'environment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zatca_tenant_credentials');
    }
};
