## Job queue. Run unique job

There is job queue component build on top of a transport. It provides some additional features:

* Stores jobs to a database. So you can query that information and build a UI for it.
* Run unique job feature. If used guarantee that there is not any job with the same name running same time.
* Sub jobs. If used allow split a big job into smaller pieces and process them asynchronously and in parallel.
* Depended job. If used allow send a message when the whole job is finished (including sub jobs).
  
Here's some  examples.
It shows how you can run unique job using job queue (The configuration is described in a dedicated chapter). 

```php
<?php 
use Enqueue\Consumption\MessageProcessorInterface;
use Enqueue\Consumption\Result;
use Enqueue\Psr\Message;
use Enqueue\Psr\Context;
use Enqueue\JobQueue\JobRunner;

class MessageProcessor implements MessageProcessorInterface
{
    /** @var JobRunner */
    private $jobRunner;

    public function process(Message $message, Context $context)
    {
        $result = $this->jobRunner->runUnique($message->getMessageId(), 'aJobName', function () {
            // do your job, there is no any other processes executing same job,

            return true; // if you want to ACK message or false to REJECT
        });

        return $result ? Result::ACK : Result::REJECT;
    }
}
```

[back to index](../index.md)