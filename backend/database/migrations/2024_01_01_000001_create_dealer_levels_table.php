<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDealerLevelsTable extends Migration
{
    public function up()
    {
        Schema::create('dealer_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->comment('等级名称');
            $table->string('code', 30)->unique()->comment('等级编码');
            $table->integer('level')->unique()->comment('等级权重，数字越大等级越高');
            $table->string('icon', 100)->nullable()->comment('等级图标');
            $table->text('description')->nullable()->comment('等级描述');
            $table->decimal('min_achievement', 15, 2)->default(0)->comment('升级所需最小业绩');
            $table->integer('min_invite_count')->default(0)->comment('升级所需最小邀请人数');
            $table->decimal('commission_rate', 5, 2)->default(0)->comment('佣金比例%');
            $table->decimal('reward_bonus', 15, 2)->default(0)->comment('升级奖励金额');
            $table->json('privileges')->nullable()->comment('等级特权配置');
            $table->boolean('is_active')->default(true)->comment('是否启用');
            $table->timestamps();
            $table->softDeletes();
            $table->index('level');
            $table->index('is_active');
        });
    }

    public function down()
    {
        Schema::dropIfExists('dealer_levels');
    }
}
