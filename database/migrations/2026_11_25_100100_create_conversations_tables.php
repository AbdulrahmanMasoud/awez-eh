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
        Schema::create('conversations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // What it is about: a property, a provider, a place, a used item or a reply to a request.
            $table->uuidMorphs('subject');
            // The resident who opened it.
            $table->foreignUuid('resident_id')->constrained('users')->cascadeOnDelete();
            // The advertiser or craftsman. Null when the platform answers, as for every brokered
            // property: the owner never appears (invariant 2).
            $table->foreignUuid('counterpart_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['open', 'closed', 'blocked'])->default('open');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('resident_read_at')->nullable();
            $table->timestamp('counterpart_read_at')->nullable();
            $table->timestamps();

            // Each side's inbox, latest first.
            $table->index(['resident_id', 'last_message_at']);
            $table->index(['counterpart_id', 'last_message_at']);
        });

        DB::statement("alter table conversations add constraint conversations_subject_type_check check (subject_type in ('property', 'provider', 'place', 'used_item', 'request_reply'))");
        // A resident has one open conversation about a subject.
        DB::statement("create unique index conversations_one_open_index on conversations (resident_id, subject_type, subject_id) where status = 'open'");

        Schema::create('messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained()->cascadeOnDelete();
            // Staff answering for the platform are users too. No action on delete: a sender's
            // messages in someone else's conversation keep their account from being deleted.
            $table->foreignUuid('sender_id')->index()->constrained('users')->noActionOnDelete();
            $table->enum('kind', ['text', 'location', 'system']);
            $table->text('body')->nullable();
            $table->geography('location', subtype: 'point', srid: 4326)->nullable();
            $table->timestamps();

            // The feed: UUID v7 keys are time-ordered (DECISIONS 53).
            $table->index(['conversation_id', 'id']);
            $table->spatialIndex('location');
        });

        DB::statement("alter table messages add constraint messages_content_check check ((kind <> 'text' or body is not null) and (kind <> 'location' or location is not null))");
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
