<?php

namespace App\Http\Controllers;

use App\Support\CommunityDirectory;

class CommunityController extends Controller
{
    public function index()
    {
        return view('communities.index', ['communities' => CommunityDirectory::query()->orderBy('community->city')->orderBy('title')->get()]);
    }

    public function show(string $slug)
    {
        $community = CommunityDirectory::query()->where('path', '/hromady/'.$slug)->firstOrFail();

        return view('communities.show', compact('community'));
    }
}
