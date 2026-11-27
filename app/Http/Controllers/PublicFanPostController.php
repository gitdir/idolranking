<?php

namespace App\Http\Controllers;

use App\Support\PublicFanPostList;
use Illuminate\Http\Request;

class PublicFanPostController extends Controller
{
    public function __invoke(Request $request, PublicFanPostList $fanPostList)
    {
        $sort = $request->string('sort')->toString();
        if (!in_array($sort, ['latest', 'monthly_liked', 'liked', 'commented'], true)) {
            $sort = 'latest';
        }

        $category = $request->query('category');
        if (!in_array($category, ['boy', 'boy_group', 'girl', 'girl_group', 'other'], true)) {
            $category = null;
        }
        $fanPosts = $fanPostList->paginate($sort, 20, $category);

        return view('fan-posts.index', compact('fanPosts', 'sort', 'category'));
    }
}
