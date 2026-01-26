<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Friendship;
use App\Models\User;
use App\Events\FriendRequestReceived;
use App\Events\FriendRequestAccepted;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FriendController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get friends where user is either the requester or the recipient
        $friendIds = Friendship::where('status', 'accepted')
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->orWhere('friend_id', $user->id);
            })
            ->get()
            ->map(function ($friendship) use ($user) {
                return $friendship->user_id === $user->id
                    ? $friendship->friend_id
                    : $friendship->user_id;
            });

        $friends = User::whereIn('id', $friendIds)
            ->get()
            ->map(fn($friend) => $this->formatUser($friend));

        return response()->json([
            'success' => true,
            'data' => $friends,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
        ]);

        $query = $request->input('q');
        $user = $request->user();

        $users = User::where('id', '!=', $user->id)
            ->where(function ($q) use ($query) {
                $q->where('name', 'ilike', "%{$query}%")
                  ->orWhere('username', 'ilike', "%{$query}%")
                  ->orWhere('first_name', 'ilike', "%{$query}%")
                  ->orWhere('last_name', 'ilike', "%{$query}%");
            })
            ->limit(20)
            ->get()
            ->map(function ($searchUser) use ($user) {
                $data = $this->formatUser($searchUser);
                $data['friendship_status'] = $this->getFriendshipStatus($user, $searchUser);
                return $data;
            });

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    public function requests(Request $request): JsonResponse
    {
        $user = $request->user();

        $requests = Friendship::where('friend_id', $user->id)
            ->where('status', 'pending')
            ->with('user')
            ->get()
            ->map(function ($friendship) {
                return [
                    'id' => $friendship->id,
                    'user' => $this->formatUser($friendship->user),
                    'created_at' => $friendship->created_at->toISOString(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    public function sendRequest(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = $request->user();
        $friendId = $request->input('user_id');

        if ($user->id === $friendId) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot send friend request to yourself',
            ], 400);
        }

        $friend = User::findOrFail($friendId);

        // Check if already friends or pending request exists
        $existingFriendship = Friendship::where(function ($query) use ($user, $friend) {
            $query->where('user_id', $user->id)
                  ->where('friend_id', $friend->id);
        })->orWhere(function ($query) use ($user, $friend) {
            $query->where('user_id', $friend->id)
                  ->where('friend_id', $user->id);
        })->first();

        if ($existingFriendship) {
            if ($existingFriendship->isAccepted()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Already friends',
                ], 400);
            }
            if ($existingFriendship->isPending()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Friend request already pending',
                ], 400);
            }
            // If rejected, allow new request
            if ($existingFriendship->status === 'rejected') {
                $existingFriendship->delete();
            }
        }

        $friendship = Friendship::create([
            'user_id' => $user->id,
            'friend_id' => $friend->id,
            'status' => 'pending',
        ]);

        // Broadcast friend request event
        broadcast(new FriendRequestReceived($friendship))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Friend request sent',
            'data' => [
                'id' => $friendship->id,
                'friend' => $this->formatUser($friend),
                'status' => 'pending',
            ],
        ], 201);
    }

    public function accept(Friendship $friendship, Request $request): JsonResponse
    {
        $user = $request->user();

        // Only the recipient can accept
        if ($friendship->friend_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if (!$friendship->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Request is not pending',
            ], 400);
        }

        $friendship->accept();

        // Broadcast acceptance event
        broadcast(new FriendRequestAccepted($friendship))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Friend request accepted',
            'data' => [
                'id' => $friendship->id,
                'friend' => $this->formatUser($friendship->user),
                'status' => 'accepted',
            ],
        ]);
    }

    public function reject(Friendship $friendship, Request $request): JsonResponse
    {
        $user = $request->user();

        // Only the recipient can reject
        if ($friendship->friend_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if (!$friendship->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Request is not pending',
            ], 400);
        }

        $friendship->reject();

        return response()->json([
            'success' => true,
            'message' => 'Friend request rejected',
        ]);
    }

    public function remove(int $friendId, Request $request): JsonResponse
    {
        $user = $request->user();

        $friendship = Friendship::where('status', 'accepted')
            ->where(function ($query) use ($user, $friendId) {
                $query->where(function ($q) use ($user, $friendId) {
                    $q->where('user_id', $user->id)
                      ->where('friend_id', $friendId);
                })->orWhere(function ($q) use ($user, $friendId) {
                    $q->where('user_id', $friendId)
                      ->where('friend_id', $user->id);
                });
            })
            ->first();

        if (!$friendship) {
            return response()->json([
                'success' => false,
                'message' => 'Friendship not found',
            ], 404);
        }

        $friendship->delete();

        return response()->json([
            'success' => true,
            'message' => 'Friend removed',
        ]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'username' => $user->username,
            'photo_url' => $user->photo_url,
        ];
    }

    private function getFriendshipStatus(User $user, User $other): ?string
    {
        $friendship = Friendship::where(function ($query) use ($user, $other) {
            $query->where('user_id', $user->id)
                  ->where('friend_id', $other->id);
        })->orWhere(function ($query) use ($user, $other) {
            $query->where('user_id', $other->id)
                  ->where('friend_id', $user->id);
        })->first();

        if (!$friendship) {
            return null;
        }

        if ($friendship->isAccepted()) {
            return 'friends';
        }

        if ($friendship->isPending()) {
            // Check who sent the request
            if ($friendship->user_id === $user->id) {
                return 'request_sent';
            }
            return 'request_received';
        }

        return null;
    }
}
