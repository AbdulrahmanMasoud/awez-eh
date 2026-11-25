<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Here rather than in the users migration, which runs before districts exist.
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignUuid('district_id')->nullable()->index()->constrained()->restrictOnDelete();
        });

        Schema::create('social_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->enum('provider', ['google', 'apple']);
            // The ID token's `sub`: the provider's stable id for the person.
            $table->string('subject');
            // As the provider gave it when the account was linked.
            $table->string('email')->nullable();
            $table->timestamp('linked_at');
            $table->timestamps();

            $table->unique(['provider', 'subject']);
            // One account per provider per user; it also indexes user_id.
            $table->unique(['user_id', 'provider']);
        });

        Schema::create('phone_verifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            // E.164: a number not confirmed yet, so not the user's phone.
            $table->string('phone');
            $table->enum('driver', ['log', 'whatsapp_inbound']);
            // An HMAC of the code, never the code (invariant 8).
            $table->string('code_hash');
            $table->smallInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->string('ip_address', 45);
            $table->timestamps();

            // The rate limits count starts per number, per user and per address in a window.
            $table->index(['phone', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['ip_address', 'created_at']);
        });

        DB::statement('alter table phone_verifications add constraint phone_verifications_attempts_check check (attempts >= 0)');

        Schema::create('device_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->index()->constrained()->cascadeOnDelete();
            // One push token per access token: signing out of a device removes its push token.
            $table->foreignId('personal_access_token_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('token')->unique();
            $table->enum('platform', ['ios', 'android']);
            $table->string('app_version')->nullable();
            $table->timestamp('last_used_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
        Schema::dropIfExists('phone_verifications');
        Schema::dropIfExists('social_accounts');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('district_id');
        });
    }
};
