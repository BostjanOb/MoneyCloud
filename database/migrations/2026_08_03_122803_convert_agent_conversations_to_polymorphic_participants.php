<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $conversationsTable = $this->conversationsTable();
        $messagesTable = $this->messagesTable();

        Schema::table($conversationsTable, function (Blueprint $table) {
            $table->dropIndex(['user_id', 'updated_at']);
            $table->renameColumn('user_id', 'participant_id');
            $table->string('participant_type')->nullable()->after('id');
        });

        Schema::table($messagesTable, function (Blueprint $table) {
            $table->dropIndex('conversation_index');
            $table->dropIndex(['user_id']);
            $table->renameColumn('user_id', 'participant_id');
            $table->string('participant_type')->nullable()->after('conversation_id');
        });

        $participantType = (new User)->getMorphClass();

        DB::table($conversationsTable)->whereNotNull('participant_id')->update(['participant_type' => $participantType]);
        DB::table($messagesTable)->whereNotNull('participant_id')->update(['participant_type' => $participantType]);

        Schema::table($conversationsTable, function (Blueprint $table) {
            $table->index(
                ['participant_type', 'participant_id', 'updated_at'],
                'participant_updated_at_index',
            );
        });

        Schema::table($messagesTable, function (Blueprint $table) {
            $table->index(
                ['conversation_id', 'participant_type', 'participant_id', 'updated_at'],
                'conversation_index',
            );

            $table->index(['participant_type', 'participant_id'], 'participant_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $conversationsTable = $this->conversationsTable();
        $messagesTable = $this->messagesTable();

        Schema::table($conversationsTable, function (Blueprint $table) {
            $table->dropIndex('participant_updated_at_index');
            $table->dropColumn('participant_type');
            $table->renameColumn('participant_id', 'user_id');
        });

        Schema::table($messagesTable, function (Blueprint $table) {
            $table->dropIndex('conversation_index');
            $table->dropIndex('participant_index');
            $table->dropColumn('participant_type');
            $table->renameColumn('participant_id', 'user_id');
        });

        Schema::table($conversationsTable, function (Blueprint $table) {
            $table->index(['user_id', 'updated_at']);
        });

        Schema::table($messagesTable, function (Blueprint $table) {
            $table->index(['conversation_id', 'user_id', 'updated_at'], 'conversation_index');
            $table->index(['user_id']);
        });
    }

    private function conversationsTable(): string
    {
        return config('ai.conversations.tables.conversations', 'agent_conversations');
    }

    private function messagesTable(): string
    {
        return config('ai.conversations.tables.messages', 'agent_conversation_messages');
    }
};
