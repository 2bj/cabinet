<?php namespace Wardrobe\Cabinet\Repositories;

use Cache;
use Config;
use Carbon\Carbon;
use Validator;
use Wardrobe\Cabinet\Entities\Post;
use Wardrobe\Cabinet\Entities\Tag;

class DbPostRepository implements PostRepositoryInterface {

	protected $post;
	protected $tag;

	public function __construct(Post $post, Tag $tag)
	{
		$this->post = $post;
		$this->tag = $tag;
	}

	/**
	 * Get all of the posts.
	 *
	 * @return array
	 */
	public function all()
	{
		return $this->post->with(array('tags', 'user'))->orderBy('publish_date', 'desc');
	}

	/**
	 * Get all of the active posts.
	 *
	 * @param int $per_page
	 *
	 * @return array
	 */
	public function active($per_page)
	{
		$per_page = is_numeric($per_page) ? $per_page : 5;

		return $this->post
			->with(array('tags', 'user'))
			->where('active', 1)
			->where('publish_date', '<=', new Carbon)
			->orderBy('publish_date', 'desc')
			->paginate($per_page);
	}

	/**
	 * Get a Post by its primary key.
	 *
	 * @param  int   $id
	 * @return Post
	 */
	public function find($id)
	{
		return $this->post->with(array('tags', 'user'))->findOrFail($id);
	}

	/**
	 * Get a Post by its slug
	 *
	 * @param  string 	$slug
	 * @return Post
	 */
	public function findBySlug($slug)
	{
		return $this->post
			->with(array('tags', 'user'))
			->where('active', 1)
			->where('publish_date', '<=', new Carbon)
			->where('slug', $slug)
			->first();
	}

	/**
	 * Get all posts with a tag
	 *
	 * @param  string   $tag
	 * @param  int      $per_page
	 * @return array
	 */
	public function activeByTag($tag, $per_page)
	{
		$per_page = is_numeric($per_page) ? $per_page : 5;

		return $this->post
			->with(array('tags', 'user'))
			->select('posts.*')
			->join('tags', 'posts.id', '=', 'tags.post_id')
			->where('tags.tag', '=', $tag)
			->orderBy('posts.publish_date', 'desc')
			->where('posts.active', 1)
			->where('posts.publish_date', '<=', new Carbon)
			->distinct()
			->paginate($per_page);
	}

	/**
	 * Search all active posts
	 *
	 * @param  string   $tag
	 * @param  int      $per_page
	 * @return array
	 */
	public function search($search, $per_page)
	{
		$per_page = is_numeric($per_page) ? $per_page : 5;

		return $this->post
			->with(array('tags', 'user'))
			->select('posts.*')
			->where(function($query) use ($search)
			{
				$query->orWhere('title', 'like', '%'.$search.'%')
							->orWhere('content', 'like', '%'.$search.'%');
			})
			->orderBy('posts.publish_date', 'desc')
			->where('posts.active', '=', 1)
			->where('posts.publish_date', '<=', new Carbon)
			->groupBy('id')
			->distinct()
			->paginate($per_page);
	}

	/**
	 * Create a new post.
	 *
	 * @param  array $data
	 * @return Post
	 */
	public function create(array $data)
	{
		$post = $this->post->create(array(
			'title'        => $data['title'],
			'content'      => $data['content'],
			'slug'         => $data['slug'],
			'active'       => $data['active'],
			'user_id'      => $data['user_id'],
			'publish_date' => $data['publish_date'],
		));

		$post->tags()->delete();

		$post->tags()->createMany($this->prepareTags($data['tags']));

		return $post;
	}

	/**
	 * Update a post's title and content.
	 *
	 * @param  int      $id
	 * @param  string   $title
	 * @param  string   $content
	 * @param  string   $slug
	 * @param  array    $tags
	 * @param  string   $active
	 * @param  int      $user_id
	 * @param  Carbon   $publish_date
	 *
	 * @return Post
	 */
	public function update(array $data)
	{
		$post = $this->find($data['id']);

		// Forget the old cache
		if (Config::get('wardrobe.cache'))
		{
			Cache::forget('post-'.$post->id);
		}

		$post->fill(array(
			'title'        => $data['title'],
			'content'      => $data['content'],
			'slug'         => $data['slug'],
			'active'       => $data['active'],
			'user_id'      => $data['user_id'],
			'publish_date' => $data['publish_date'],
		))->save();

		$post->tags()->delete();

		$post->tags()->createMany($this->prepareTags($data['tags']));

		return $post;
	}

	/**
	 * Prepare an array of tags for database storage.
	 *
	 * @param  array  $tags
	 * @return array
	 */
	protected function prepareTags(array $tags)
	{
		$results = array();

		foreach ($tags as $tag)
		{
			$results[] = compact('tag');
		}

		return $results;
	}

	/**
	 * Delete the post with the given ID.
	 *
	 * @param  int  $id
	 * @return void
	 */
	public function delete($id)
	{
		$this->post->where('id', $id)->delete();
	}

	/**
	 * Get a list of all of the tags used by the blog.
	 *
	 * @return array
	 */
	public function allTags()
	{
		return $this->tag->orderBy('tag', 'asc')->groupBy('tag')->distinct()->get()->toArray();
	}

	/**
	 * Determine if the given post is valid for creation.
	 *
	 * @param  string  $title
	 * @param  string  $slug
	 * @return \Illuminate\Support\MessageBag
	 */
	public function validForCreation($title, $slug)
	{
		return $this->validatePost($title, $slug);
	}

	/**
	 * Determine if a given post is valid for updating.
	 *
	 * @param  string  $title
	 * @param  string  $slug
	 * @param  int  $id
	 * @return \Illuminate\Support\MessageBag
	 */
	public function validForUpdate($id, $title, $slug)
	{
		return $this->validatePost($title, $slug, $id);
	}

	/**
	 * Determine if the given post is valid.
	 *
	 * @param  string  $title
	 * @param  string  $slug
	 * @param  int  $id
	 * @return \Illuminate\Support\MessageBag
	 */
	protected function validatePost($title, $slug, $id = null)
	{
		$rules = array(
			'title' => 'required',
			'slug'  => 'required|unique:posts,slug',
		);

		if ($id)
		{
			$rules['slug'] .= ','.$id;
		}

		with($validator = Validator::make(compact('title', 'slug'), $rules))->fails();

		return $validator->errors();
	}

}
