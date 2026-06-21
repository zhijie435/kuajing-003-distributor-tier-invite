<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInviteCodesTable extends Migration
{
    public function up()
    {
        Schema::create('invite_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique()->comment('邀请码');
            $table->unsignedBigInteger('owner_id')->comment('拥有者用户ID');
            $table->unsignedBigInteger('target_dealer_level_id')->nullable()->comment('绑定的目标等级ID');
            $table->integer('max_uses')->default(1)->comment('最大使用次数');
            $table->integer('used_count')->default(0)->comment('已使用次数');
            $table->decimal('reward_amount', 15, 2)->default(0)->comment('邀请奖励金额');
            $table->decimal('new_user_bonus', 15, 2)->default(0)->comment('新用户注册奖励');
            $table->timestamp('expires_at')->nullable()->comment('过期时间');
            $table->timestamp('activated_at')->nullable()->comment('激活时间');
            $table->tinyInteger('status')->default(1)->comment('状态：0禁用 1正常 2已用完 3已过期');
            $table->text('remark')->nullable()->comment('备注');
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建人ID');
            $table->timestamps();
            $table->softDeletes();
            $table->index('code');
            $table->index('owner_id');
            $table->index('target_dealer_level_id');
            $table->index('status');
            $table->index('expires_at');
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('target_dealer_level_id')->references('id')->on('dealer_levels')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('invite_codes');
    }
}
