<?php

namespace App\Http\Controllers\Management\Wall;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Models\Wall\PostModel;

class PostsController extends Controller{

	public $prefix_table = 'table';

	public function index(){
		$posts = PostModel::paginate(15);
		return view('management.wall.posts.index',[
			'posts'=>$posts
		]);
	}

	public function create(){
		return view('management.wall.posts.create');
	}

	public function edit($id){
		$post = PostModel::find($id);
		return view('management.wall.posts.edit',[
			'post'=>$post
		]);
	}

	public function store(){
		$post = new PostModel;
		$post->save();
		return redirect()->route('posts.edit', $post->id);
	}
}