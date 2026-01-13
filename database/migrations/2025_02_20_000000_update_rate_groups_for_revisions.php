<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1) rate_groups
        if (! Schema::hasColumn('rate_groups', 'published_at')) {
            Schema::table('rate_groups', function (Blueprint $table) {
                $table->timestamp('published_at')->nullable()->after('title');
            });
        }

        if (! Schema::hasColumn('rate_groups', 'revision_of')) {
            Schema::table('rate_groups', function (Blueprint $table) {
                $table->foreignId('revision_of')
                    ->nullable()
                    ->after('published_at')
                    ->constrained('rate_groups');
            });
        }

        if (! Schema::hasColumn('rate_groups', 'position')) {
            Schema::table('rate_groups', function (Blueprint $table) {
                $table->unsignedInteger('position')->nullable()->after('revision_of');
            });
        }

        // 2) columns
        if (! Schema::hasColumn('columns', 'rate_group_id')) {
            Schema::table('columns', function (Blueprint $table) {
                $table->foreignId('rate_group_id')->nullable()->after('organization_id');
                $table->foreign('rate_group_id')->references('id')->on('rate_groups');
            });
        }

        // 3) organizations (the one that failed before)
        if (! Schema::hasColumn('organizations', 'default_rate_group_id')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->foreignId('default_rate_group_id')
                    ->nullable()
                    ->constrained('rate_groups');
            });
        }
    }

    public function down()
    {
        Schema::table('organizations', function (Blueprint $table) {
            if (Schema::hasColumn('organizations', 'default_rate_group_id')) {
                $table->dropConstrainedForeignId('default_rate_group_id');
            }
        });

        Schema::table('columns', function (Blueprint $table) {
            if (Schema::hasColumn('columns', 'rate_group_id')) {
                $table->dropForeign(['rate_group_id']);
                $table->dropColumn('rate_group_id');
            }
        });

        Schema::table('rate_groups', function (Blueprint $table) {
            if (Schema::hasColumn('rate_groups', 'revision_of')) {
                $table->dropConstrainedForeignId('revision_of');
            }

            if (Schema::hasColumn('rate_groups', 'published_at')) {
                $table->dropColumn('published_at');
            }

            if (Schema::hasColumn('rate_groups', 'position')) {
                $table->dropColumn('position');
            }
        });
    }
};
