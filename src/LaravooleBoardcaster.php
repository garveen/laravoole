<?php

namespace Laravoole;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;

use Laravoole\LaravooleFacade;

class LaravooleBroadcaster extends Broadcaster
{
    /**
     * Authenticate the incoming request for a given channel.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function auth($request)
    {
        if (Str::startsWith($request->channel_name, ['private-', 'presence-']) &&
            ! $request->user()) {
            throw new HttpException(403);
        }

        return parent::verifyUserCanAccessChannel(
            $request, str_replace(['private-', 'presence-'], '', $request->channel_name)
        );
    }

    /**
     * Return the valid authentication response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $result
     * @return mixed
     */
    public function validAuthenticationResponse($request, $result)
    {
        if (is_bool($result)) {
            return json_encode($result);
        }

        return json_encode(['channel_data' => [
            'user_id' => $request->user()->getKey(),
            'user_info' => $result,
        ]]);
    }

    /**
     * Broadcast the given event.
     *
     * @param  array  $channels
     * @param  string  $event
     * @param  array  $payload
     * @return void
     */
    public function broadcast(array $channels, $event, array $payload = [])
    {


        $socket = Arr::pull($payload, 'socket');

        $payload = [
            'event' => $event, 'data' => $payload, 'socket' => $socket,
        ];

        foreach ($this->formatChannels($channels) as $channel) {
            LaravooleFacade::task(['channel' => $channel, 'payload' => $payload]);
        }
    }
}
