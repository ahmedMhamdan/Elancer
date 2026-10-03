<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Each member may listen only to their own channel.
Broadcast::channel('workspace.{id}', fn (User $user, int $id) => $user->id === $id);
