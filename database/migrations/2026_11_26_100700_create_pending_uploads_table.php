<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A photo a resident uploaded before the listing that will hold it exists. Its file is a media
 * row of its own; AttachUploads hands that row to the listing, and the hourly cleanup deletes
 * what was never attached.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_uploads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->index()->constrained()->cascadeOnDelete();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_uploads');
    }
};
