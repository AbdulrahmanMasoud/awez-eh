<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Edited by step 1.4 rather than altered by a second migration: nothing was deployed yet
        // (DECISIONS 21). `district_id` comes with the Identity tables, once districts exist.
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            // Apple may give a private relay address, or none on later sign-ins.
            $table->string('email')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            // Only admins have one (Filament, step 1.5): residents sign in with Google or Apple.
            $table->string('password')->nullable();
            $table->rememberToken();
            // A confirmed mobile number only (E.164); one being confirmed lives in phone_verifications.
            $table->string('phone')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            // The provider's picture; uploads come with media (step 2.1).
            $table->text('avatar_url')->nullable();
            $table->enum('locale', ['ar', 'en'])->default('ar');
            $table->enum('status', ['active', 'suspended', 'deleted'])->default('active');
            $table->timestamp('suspended_at')->nullable();
            // When the account was deleted and anonymized. Not soft deletes: the row stays
            // visible to the histories that point at it (DECISIONS 56).
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        // Unique where present: many accounts have no email or no confirmed number. Emails are
        // stored lowercased, so the index needs no lower().
        DB::statement('create unique index users_email_unique on users (email) where email is not null');
        DB::statement('create unique index users_phone_unique on users (phone) where phone is not null');
        DB::statement('alter table users add constraint users_phone_verified_check check ((phone is null) = (phone_verified_at is null))');
        DB::statement("alter table users add constraint users_suspended_check check ((status = 'suspended') = (suspended_at is not null))");
        DB::statement("alter table users add constraint users_deleted_check check ((status = 'deleted') = (deleted_at is not null))");

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // sessions references users, so it goes first.
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
