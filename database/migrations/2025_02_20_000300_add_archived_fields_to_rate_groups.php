<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('rate_groups', 'archived_at')) {
            Schema::table('rate_groups', function (Blueprint $table) {
                $table->timestamp('archived_at')->nullable()->after('published_at');
            });
        }

        if (! Schema::hasColumn('rate_groups', 'superseded_by')) {
            Schema::table('rate_groups', function (Blueprint $table) {
                $table->foreignId('superseded_by')
                    ->nullable()
                    ->after('archived_at')
                    ->constrained('rate_groups');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('rate_groups', 'superseded_by')) {
            Schema::table('rate_groups', function (Blueprint $table) {
                $table->dropConstrainedForeignId('superseded_by');
            });
        }

        if (Schema::hasColumn('rate_groups', 'archived_at')) {
            Schema::table('rate_groups', function (Blueprint $table) {
                $table->dropColumn('archived_at');
            });
        }
    }
};
