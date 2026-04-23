<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zatca_tenant_notification_hooks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('zatca_tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('channel')->default('webhook');
            $table->string('target_url');
            $table->json('events')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('secret')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zatca_tenant_notification_hooks');
    }
};
