<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bunny_sidecar_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('member');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('bunny_sidecar_password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('bunny_sidecar_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('api_key');
            $table->timestamps();
        });

        Schema::create('bunny_sidecar_zone_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('bunny_sidecar_users')->cascadeOnDelete();
            $table->foreignId('bunny_account_id')->constrained('bunny_sidecar_accounts')->cascadeOnDelete();
            $table->string('zone_domain');
            $table->json('permissions');
            $table->timestamps();

            $table->unique(['user_id', 'bunny_account_id', 'zone_domain']);
        });

        Schema::create('bunny_sidecar_invitations', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->index();
            $table->string('name')->nullable();
            $table->string('role');
            $table->string('token', 64)->unique();
            $table->foreignId('invited_by')->nullable()->constrained('bunny_sidecar_users')->nullOnDelete();
            $table->json('zone_grants')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bunny_sidecar_record_ownerships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bunny_account_id')->constrained('bunny_sidecar_accounts')->cascadeOnDelete();
            $table->string('zone_domain');
            $table->unsignedBigInteger('record_id');
            $table->foreignId('created_by')->nullable()->constrained('bunny_sidecar_users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['bunny_account_id', 'zone_domain', 'record_id']);
        });

        Schema::create('bunny_sidecar_record_managers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bunny_account_id')->constrained('bunny_sidecar_accounts')->cascadeOnDelete();
            $table->string('zone_domain');
            $table->unsignedBigInteger('record_id');
            $table->foreignId('user_id')->constrained('bunny_sidecar_users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['bunny_account_id', 'zone_domain', 'record_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bunny_sidecar_record_managers');
        Schema::dropIfExists('bunny_sidecar_record_ownerships');
        Schema::dropIfExists('bunny_sidecar_invitations');
        Schema::dropIfExists('bunny_sidecar_zone_grants');
        Schema::dropIfExists('bunny_sidecar_accounts');
        Schema::dropIfExists('bunny_sidecar_password_reset_tokens');
        Schema::dropIfExists('bunny_sidecar_users');
    }
};
