<?php

namespace App\Http\Controllers;

use App\Support\PublicFanPostList;
use Illuminate\Http\Request;

class PublicFanPostController extends Controller
{
    public function __invoke(Request $request, PublicFanPostList $fanPostList)
    {
        $sort = $request->string('sort')->toString();
        if (!in_array($sort, ['latest', 'liked', 'commented'], true)) {
            $sort = 'latest';
        }

        $fanPosts = $fanPostList->paginate($sort);

        return view('fan-posts.index', compact('fanPosts', 'sort'));
    }
}
