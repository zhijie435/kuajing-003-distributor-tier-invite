<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUpgradeRecordsTable extends Migration
{
    public function up()
    {
        Schema::create('upgrade_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->unsignedBigInteger('old_level_id')->nullable()->comment('原等级ID');
            $table->unsignedBigInteger('new_level_id')->comment('新等级ID');
            $table->tinyInteger('upgrade_type')->default(1)->comment('升级类型：1自动升级 2手动升级 3邀请码升级 4后台调整');
            $table->decimal('achievement_at_upgrade', 15, 2)->default(0)->comment('升级时业绩');
            $table->integer('invite_count_at_upgrade')->default(0)->comment('升级时邀请人数');
            $table->decimal('reward_bonus', 15, 2)->default(0)->comment('升级奖励金额');
            $table->boolean('is_rewarded')->default(false)->comment('奖励是否已发放');
            $table->timestamp('rewarded_at')->nullable()->comment('奖励发放时间');
            $table->unsignedBigInteger('operator_id')->nullable()->comment('操作人ID');
            $table->unsignedBigInteger('invite_code_id')->nullable()->comment('关联邀请码ID');
            $table->text('remark')->nullable()->comment('备注');
            $table->timestamps();
            $table->index('user_id');
            $table->index('old_level_id');
            $table->index('new_level_id');
            $table->index('upgrade_type');
            $table->index('created_at');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('old_level_id')->references('id')->on('dealer_levels')->nullOnDelete();
            $table->foreign('new_level_id')->references('id')->on('dealer_levels')->onDelete('restrict');
            $table->foreign('operator_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('invite_code_id')->references('id')->on('invite_codes')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('upgrade_records');
    }
}
