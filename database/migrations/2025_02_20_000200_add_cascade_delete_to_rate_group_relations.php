<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if ($this->isSqlite()) {
            return;
        }

        if (Schema::hasColumn('rates', 'rate_group_id')) {
            Schema::table('rates', function (Blueprint $table) {
                $table->dropForeign(['rate_group_id']);
                $table->foreign('rate_group_id')
                    ->references('id')
                    ->on('rate_groups')
                    ->onDelete('cascade');
            });
        }

        if (Schema::hasColumn('columns', 'rate_group_id')) {
            Schema::table('columns', function (Blueprint $table) {
                $table->dropForeign(['rate_group_id']);
                $table->foreign('rate_group_id')
                    ->references('id')
                    ->on('rate_groups')
                    ->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        if ($this->isSqlite()) {
            return;
        }

        if (Schema::hasColumn('rates', 'rate_group_id')) {
            Schema::table('rates', function (Blueprint $table) {
                $table->dropForeign(['rate_group_id']);
                $table->foreign('rate_group_id')
                    ->references('id')
                    ->on('rate_groups');
            });
        }

        if (Schema::hasColumn('columns', 'rate_group_id')) {
            Schema::table('columns', function (Blueprint $table) {
                $table->dropForeign(['rate_group_id']);
                $table->foreign('rate_group_id')
                    ->references('id')
                    ->on('rate_groups');
            });
        }
    }

    private function isSqlite(): bool
    {
        return Schema::getConnection()->getDriverName() === 'sqlite';
    }
};
