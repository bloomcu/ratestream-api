<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('rate_groups', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('title');
            $table->foreignId('revision_of')->nullable()->after('published_at')->constrained('rate_groups');
            $table->unsignedInteger('position')->nullable()->after('revision_of');
        });

        Schema::table('columns', function (Blueprint $table) {
            $table->foreignId('rate_group_id')->nullable()->after('organization_id');
            $table->foreign('rate_group_id')->references('id')->on('rate_groups');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->foreignId('default_rate_group_id')->nullable()->after('user_id')->constrained('rate_groups');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_rate_group_id');
        });

        Schema::table('columns', function (Blueprint $table) {
            $table->dropForeign(['rate_group_id']);
            $table->dropColumn('rate_group_id');
        });

        Schema::table('rate_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revision_of');
            $table->dropColumn(['published_at', 'position']);
        });
    }
};
