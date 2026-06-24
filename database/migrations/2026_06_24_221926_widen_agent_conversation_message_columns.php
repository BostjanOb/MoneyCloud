<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columns that hold AI message payloads (content + tool call/result JSON).
     * The original schema declared these as TEXT (64 KB), which the advisor's
     * tool results can exceed, causing "Data too long" insert failures.
     *
     * @var array<int, string>
     */
    private array $columns = [
        'content',
        'attachments',
        'tool_calls',
        'tool_results',
        'usage',
        'meta',
    ];

    public function up(): void
    {
        $messagesTable = config('ai.conversations.tables.messages', 'agent_conversation_messages');

        Schema::table($messagesTable, function (Blueprint $table): void {
            foreach ($this->columns as $column) {
                $table->longText($column)->change();
            }
        });
    }

    public function down(): void
    {
        $messagesTable = config('ai.conversations.tables.messages', 'agent_conversation_messages');

        Schema::table($messagesTable, function (Blueprint $table): void {
            foreach ($this->columns as $column) {
                $table->text($column)->change();
            }
        });
    }
};
