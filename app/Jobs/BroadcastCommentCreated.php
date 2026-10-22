<?php

namespace App\Jobs;

use Illuminate\Broadcasting\Channel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastingFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BroadcastCommentCreated implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public int $commentId, public int $rootId)
    {
        $this->onQueue('broadcasts');
    }

    public function handle(BroadcastingFactory $broadcasting): void
    {
        $broadcasting->connection('reverb')->broadcast(
            [new Channel('comments')],
            'comment.created',
            ['comment_id' => $this->commentId, 'root_id' => $this->rootId],
        );
    }
}
