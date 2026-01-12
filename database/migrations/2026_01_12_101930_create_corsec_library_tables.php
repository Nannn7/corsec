<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('corsec_library_categories', function (Blueprint $table) {
            $table->id();

            $table->string('type')->index(); // internal/eksternal/reference/media
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('status')->default(true)->index();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('corsec_library_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')->constrained('corsec_library_categories')->cascadeOnDelete();

            $table->string('title')->index();
            $table->text('description')->nullable();

            $table->string('item_type')->default('link')->index(); // link/file
            $table->string('url')->nullable();

            $table->foreignId('attachment_id')->nullable()->constrained('corsec_attachments')->nullOnDelete();

            $table->timestamp('published_at')->nullable()->index();
            $table->boolean('status')->default(true)->index();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corsec_library_items');
        Schema::dropIfExists('corsec_library_categories');
    }
};
