<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusAndOperationLogsToInviteChainsAndUpgradeRecords extends Migration
{
    public function up()
    {
        Schema::table('invite_chains', function (Blueprint $table) {
            $table->tinyInteger('status')->default(1)->comment('状态：1待确认 2已确认 3已取消 4已发放奖励')->after('is_rewarded');
            $table->unsignedBigInteger('operator_id')->nullable()->comment('最后操作人ID')->after('status');
            $table->json('operation_logs')->nullable()->comment('操作记录日志，JSON数组')->after('operator_id');
            $table->timestamp('confirmed_at')->nullable()->comment('确认时间')->after('operation_logs');
            $table->timestamp('cancelled_at')->nullable()->comment('取消时间')->after('confirmed_at');

            $table->index('status');
            $table->foreign('operator_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('upgrade_records', function (Blueprint $table) {
            $table->tinyInteger('status')->default(1)->comment('状态：1待审核 2审核通过 3审核拒绝 4已发放奖励')->after('is_rewarded');
            $table->json('operation_logs')->nullable()->comment('操作记录日志，JSON数组')->after('remark');
            $table->timestamp('reviewed_at')->nullable()->comment('审核时间')->after('operation_logs');
            $table->unsignedBigInteger('reviewer_id')->nullable()->comment('审核人ID')->after('reviewed_at');

            $table->index('status');
            $table->foreign('reviewer_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('invite_chains', function (Blueprint $table) {
            $table->dropForeign(['operator_id']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'status',
                'operator_id',
                'operation_logs',
                'confirmed_at',
                'cancelled_at',
            ]);
        });

        Schema::table('upgrade_records', function (Blueprint $table) {
            $table->dropForeign(['reviewer_id']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'status',
                'operation_logs',
                'reviewed_at',
                'reviewer_id',
            ]);
        });
    }
}
