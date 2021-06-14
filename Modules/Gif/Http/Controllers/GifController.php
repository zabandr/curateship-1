<?php

namespace Modules\Gif\Http\Controllers;

use Arr, Str, Image, File, Imagick;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\Controller;
use Modules\Post\Entities\{ PostSetting, Post, PostsTag };
use Modules\Tag\Entities\{Tag, TagCategory};

class GifController extends Controller
{

    public function cleanupEditorImages()
    {
        // `$directory` path value must be the value from `uploadImage` method
        // `uploadImage` method at app/Http/Controllers/EditorjsController.php

        $directory = 'public/editorjs-images';
        $files     = Storage::files($directory);

        foreach ($files as $file) {
            $file_name    = basename($file);
            $file_on_gif = Post::where( 'post_type', 'gif' )->firstWhere('description', 'LIKE', '%' . $file_name . '%');

            // model is null -> delete
            if (!$file_on_gif) {
                Storage::delete($file);
            }
        }
    }

    public function index()
    {
        // Cleanup unused images created with editorjs
        $this->cleanupEditorImages();

        $view = request()->ajax() ? 'gif::partials.table' : 'gif::index';

        $gifs = Post::leftJoin('users', 'posts.user_id', '=', 'users.id')
            ->select([
                'posts.id',
                'title',
                'slug',
                'posts.created_at as created_at',
                'thumbnail',
                'thumbnail_medium',
                'is_deleted',
                'is_pending',
                'is_published',
                'users.username as username'
            ])->orderBy('created_at', 'desc');

        // Get only 'post' type
        $gifs->where( 'post_type', 'gif' );

        if(request()->has('gifsearch')){
            $gifs->where('title', 'LIKE', '%' . request('gifsearch') . '%')
            ->orWhere('users.name', 'LIKE', '%' . request('gifsearch') . '%');
        }

        $gifs = (request()->has('is_trashed'))
            ? $gifs->where('is_deleted', 1)
            : $gifs->where('is_deleted', 0);

        if(!request()->has('is_trashed')){
            $gifs = (request()->has('is_draft'))
                ? $gifs->where('is_published', 0)->where('is_pending', 0)
                : (request()->has('is_pending') ? $gifs->where('is_published', 0)->where('is_pending', 1)->where('is_rejected', 0) : $gifs->where('is_published', 1));
        }

        $limit = request('limit') ? request('limit') : 25;

        $gifs = $gifs->paginate($limit);

        $gifs_published_count = Post::where( 'post_type', 'gif' )->where('is_deleted', 0)->where('is_published', 1)->count();
        $gifs_draft_count = Post::where( 'post_type', 'gif' )->where('is_deleted', 0)->where('is_published', 0)->where('is_pending', 0)->count();
        $gifs_pending_count = Post::where( 'post_type', 'gif' )->where('is_deleted', 0)->where('is_published', 0)->where('is_pending', 1)->count();
        $gifs_deleted_count = Post::where( 'post_type', 'gif' )->where('is_deleted', 1)->count();

        $availableLimit = ['25', '50', '100', '150', '200'];

        $image_width = '40';
        $image_height = '40';
        $gifs_settings = PostSetting::where( 'post_type', 'gif' )->first();
        if(!is_null($gifs_settings)){
            $image_width = $gifs_settings->medium_width;
            $image_height = $gifs_settings->medium_height;
        }

        $request    = request();
        $is_trashed = request('is_trashed');
        $is_draft   = request('is_draft');
        $is_pending   = request('is_pending');

        $tag_categories = TagCategory::all();

        // get all tags
        $tags_by_category = array();
        $tags = Tag::where('published', true)->orderBy('name', 'asc')->get();
        foreach($tags as $tag) {
            if (!isset($tags_by_category[$tag->tag_category_id]))
                $tags_by_category[$tag->tag_category_id] = array();
            $tags_by_category[$tag->tag_category_id][] = $tag->name;
        }
        $tags_by_category = json_encode($tags_by_category);

        // Generate `slug` if it's not yet set
        foreach ($gifs as $gif) {
            if (!$gif->slug) {
                $slug                = Str::slug($gif->title, '-');
                $gif_with_same_slug = Post::where('slug', $slug)->where('id', '<>', $gif->id)->first();

                if ($gif_with_same_slug) {
                    $slug .= '-2';
                }

                $gif->slug = $slug;
                $gif->save();
            }
        }

        // get rejected gifs
        $rejected_gifs = [];
        if ($is_pending) {
            $rejected_gifs = $this->getRejectedGifs();
        }

        return view($view, compact(
            'gifs', 'rejected_gifs', 'gifs_published_count', 'gifs_draft_count', 'gifs_pending_count', 'gifs_deleted_count',
            'availableLimit', 'limit', 'image_width', 'image_height', 'request', 'is_trashed', 'is_draft', 'is_pending', 'tag_categories', 'tags_by_category'
            )
        );
    }

    public function settings()
    {
        $gifs_published_count = Post::where( 'post_type', 'gif' )->where('is_deleted', 0)->where('is_published', 1)->count();
        $gifs_draft_count = Post::where( 'post_type', 'gif' )->where('is_deleted', 0)->where('is_published', 0)->where('is_pending', 0)->count();
        $gifs_pending_count = Post::where( 'post_type', 'gif' )->where('is_deleted', 0)->where('is_published', 0)->where('is_pending', 1)->count();
        $gifs_deleted_count = Post::where( 'post_type', 'gif' )->where('is_deleted', 1)->count();

        $gifs_settings = PostSetting::where( 'post_type', 'gif' )->first();

        return view('gif::layouts.settings', compact(
            'gifs_published_count', 'gifs_draft_count', 'gifs_pending_count', 'gifs_deleted_count', 'gifs_settings'
            )
        );
    }

    /**
     * Store the gifs settings.
     * @return view
     */
    public function settingsStore()
    {
        return $this->createOrUpdateSettings('create', 'created');
    }

    /**
     * Update the gifs settings.
     * @return view
     */
    public function settingsUpdate()
    {
        return $this->createOrUpdateSettings('update', 'updated');
    }

    /**
     * Store or update gifs settings.
     * @return view
     */
    public function createOrUpdateSettings($method, $message)
    {
        $this->validate(request(), [
            'medium_width' => 'required|max:255',
            'medium_height' => 'required|max:255',
            // 'image_setting' => 'in:maintain,crop'
        ]);

        if($method == 'create'){
            // Add 'gif' post_type.
            PostSetting::create([
                'medium_width'     => request('medium_width'),
                'medium_height'    => request('medium_height'),
                'post_type'        => 'gif'
            ]);    
        } else{
            $gifs_settings = PostSetting::where('post_type', 'gif')->first();
            $gifs_settings->update(request()->except(['_token']));
        }

        return redirect('/admin/gifs/settings')->with("gifs-settings-alert", "Settings has been $message!");
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        return view('gif::create');
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store()
    {

        $this->validate(request(), [
            'title' => 'required|max:255',
        ]);

        if(request()->has('thumbnail')){
            $settings_width = 40;
            $settings_height = 40;

            if(!is_null($gifs_settings = PostSetting::where( 'post_type', 'gif' )->first())){
                $settings_width = $gifs_settings->medium_width;
                $settings_height = $gifs_settings->medium_height;
            }

            $gif_path = storage_path() . '/app/public/gifs';

            // Ensure that original, and thumbnail folder exists
            File::ensureDirectoryExists($gif_path . '/original');
            File::ensureDirectoryExists($gif_path . '/thumbnail');

            // Save orignal image to file system
            $thumbnail = request()->file('thumbnail')->store('public/gifs/original');
            $thumbnail_name = Arr::last(explode('/', $thumbnail));

            // Save thumbnail (medium) image to file system
            $thumbnail_medium = new Imagick($gif_path . '/original/' . $thumbnail_name);
            $thumbnail_medium = $thumbnail_medium->coalesceImages();
            do {
                $thumbnail_medium->cropThumbnailImage( $settings_width, $settings_height );
            } while ( $thumbnail_medium->nextImage());

            $thumbnail_medium = $thumbnail_medium->deconstructImages();
            $thumbnail_medium_name = 'thumbnailcrop' . Str::random(27) . '.' . Arr::last(explode('.', $thumbnail));
            $thumbnail_medium->writeImages($gif_path . '/thumbnail/' . $thumbnail_medium_name, true);
        }

        // Generate slug
        $slug               = Str::slug(strip_tags(request('title')), '-');
        $gif_with_same_slug = Post::firstWhere('slug', $slug);

        if ($gif_with_same_slug) {
            $slug .= '-2';
        }

        $gif = Post::create([
            'user_id'          => auth()->user()->id,
            'title'            => strip_tags(request('title')),
            'slug'             => $slug,
            'description'      => request('description'),
            'thumbnail'        => (request()->has('thumbnail')) ? $thumbnail_name : NULL,
            'thumbnail_medium' => (request()->has('thumbnail')) ? $thumbnail_medium_name : NULL,
            'seo_page_title'   => request('page_title') ?: NULL,
            'tags'             => (request()->has('tags')) ? implode(',', request('tags')) : NULL,
            'post_type'        => 'gif',
            'is_pending'       => 0,
            'is_published'     => request('is_published')
        ]);

        $tag_categories = TagCategory::all();

        foreach ($tag_categories as $key => $tag_category) {
            if (request()->has('tag_category_' . $tag_category->id)) {

                $tags_input = request('tag_category_' . $tag_category->id);

                foreach ($tags_input as $key => $tag_input) {
                    $tag = Tag::firstWhere('name', $tag_input);

                    // If tag doesn't exist yet, create it
                    if (!$tag) {
                        $tag                  = new Tag;
                        $tag->name            = $tag_input;
                        $tag->tag_category_id = $tag_category->id;
                        $tag->published       = true;
                        $tag->save();
                    }

                    // Insert posts_tag
                    $gifs_tag          = new PostsTag;
                    $gifs_tag->post_id = $gif->id;
                    $gifs_tag->tag_id  = $tag->id;

                    $gifs_tag->save();
                }
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Gif has been created!'
        ]);
    }

    public function fetchDataAjax($id)
    {
        $gif = Post::where( 'post_type', 'gif' )->find($id);

        if(!$gif){
            return response()->json([
                'status' => false,
                'message' => 'Gif does not exists.'
            ]);
        }

        $data = [];

        $data['id']           = $gif->id;
        $data['title']        = $gif->title;
        $data['slug']         = $gif->slug;
        $data['description']  = html_entity_decode($gif->description);
        $data['thumbnail']    = asset("storage/gifs/original/{$gif->thumbnail}");
        $data['page_title']   = $gif->seo_page_title;
        $data['post_date']    = Date('d/m/Y', strtotime($gif->created_at));
        $data['is_published'] = $gif->is_published;
        $data['is_deleted']   = $gif->is_deleted;

        $tag_categories        = TagCategory::all();
        $gifs_tags            = $gif->postsTag()->get();
        $all_tags_per_category = [];

        foreach ($tag_categories as $key => $tag_category) {
            $tags_per_category = '';

            foreach ($gifs_tags as $gif_tags_key => $gifs_tag) {
                $tag = Tag::find($gifs_tag->tag_id);

                // If they belong to the current tag category -> append
                if ($tag->tag_category_id == $tag_category->id) {
                    $tags_per_category .= $tag->name . ',';
                }
            }

            $tags_per_category = rtrim($tags_per_category, ',');

            $tags_html = ($tags_per_category) ?
                        '<option selected>' .
                            implode('</option><option selected>',
                                explode(',', $tags_per_category)
                            ) .
                        '</option>' : '';

            array_push(
                $all_tags_per_category,
                [
                    'tag_category_id' => $tag_category->id,
                    'tags'            => $tags_html
                ]
            );

        }

        $data['tags'] = json_encode($all_tags_per_category);

        return $data;
    }

    public function ajaxUpdate()
    {
        $gif = Post::where( 'post_type', 'gif' )->find(request('id'));

        if(!$gif){
            return response()->json([
                'status' => false,
                'message' => 'Gif does not exists!'
            ]);            
        }

        if(request()->has('thumbnail')){
            $settings_width = 40;
            $settings_height = 40;

            if(!is_null($gifs_settings = PostSetting::where( 'post_type', 'gif' )->first())){
                $settings_width = $gifs_settings->medium_width;
                $settings_height = $gifs_settings->medium_height;
            }

            $gif_path = storage_path() . '/app/public/gifs';

            // Ensure that original, and thumbnail folder exists
            File::ensureDirectoryExists($gif_path . '/original');
            File::ensureDirectoryExists($gif_path . '/thumbnail');

            // Save orignal image to file system
            $thumbnail = request()->file('thumbnail')->store('public/gifs/original');
            $thumbnail_name = Arr::last(explode('/', $thumbnail));

            // Save thumbnail (medium) image to file system
            $thumbnail_medium = new Imagick($gif_path . '/original/' . $thumbnail_name);
            $thumbnail_medium = $thumbnail_medium->coalesceImages();
            do {
                $thumbnail_medium->cropThumbnailImage( $settings_width, $settings_height );
            } while ( $thumbnail_medium->nextImage());

            $thumbnail_medium = $thumbnail_medium->deconstructImages();
            $thumbnail_medium_name = 'thumbnailcrop' . Str::random(27) . '.' . Arr::last(explode('.', $thumbnail));
            $thumbnail_medium->writeImages($gif_path . '/thumbnail/' . $thumbnail_medium_name, true);

            // Delete thumbnail if exists.
            if(file_exists($gif->getThumbnail())){
                unlink($gif->getThumbnail());
            }

            if(file_exists($gif->getThumbnail('medium'))){
                unlink($gif->getThumbnail('medium'));
            }
        }

        $is_published = request('is_published') ?? $gif->is_published;
	    $is_pending = 0;
	    $is_rejected = 0;

        // Generate slug
        $slug                = Str::slug(request('slug'), '-');
        $gif_with_same_slug = Post::where('slug', $slug)->where('id', '<>', $gif->id)->first();

        if ($gif_with_same_slug) {
            $slug .= '-2';
        }

        // change Gif Created Time "created_at"
        $created_time = strtotime($gif->created_at);
        $created_h = date("H", $created_time);
        $created_m = date("i", $created_time);
        $created_s = date("s", $created_time);

        if (request('post_date')) {
            $date_array = explode('/', request('post_date'));

            $day = $date_array[0];
            $month = $date_array[1];
            $year = $date_array[2];

        } else {
            $day = date("d", time());
            $month = date("m", time());
            $year = date("Y", time());
        }

        $datetime_format = "%s/%s/%s %s:%s:%s";
        $post_date = strtotime(sprintf($datetime_format, $year, $month, $day, $created_h, $created_m, $created_s));

        $gif->update([
            'title' => strip_tags(request('title')),
            'slug' => $slug,
            'description' => request('description'),
            'thumbnail' => (request()->has('thumbnail')) ? $thumbnail_name : $gif->thumbnail,
            'thumbnail_medium' => (request()->has('thumbnail')) ? $thumbnail_medium_name : $gif->thumbnail_medium,
            'seo_page_title' => request('page_title') ?: NULL,
            'tags' => (request()->has('tags')) ? implode(',', request('tags')) : NULL,
            'created_at' => $post_date,
            'is_published' => $is_published,
            'is_pending' => $is_pending,
            'is_rejected' => $is_rejected
        ]);

        $tag_categories = TagCategory::all();

        // Delete all previous tags on `posts_tags` table with this gif
        $delete_gifs_tags = PostsTag::where('post_id', $gif->id)->delete();

        foreach ($tag_categories as $key => $tag_category) {
            if (request()->has('tag_category_' . $tag_category->id)) {

                $tags_input = request('tag_category_' . $tag_category->id);

                foreach ($tags_input as $key => $tag_input) {
                    $tag = Tag::where(
                        [
                            'name'            => $tag_input,
                            'tag_category_id' => $tag_category->id
                        ]
                    )->first();

                    // If tag doesn't exist yet, create it
                    if (!$tag) {
                        $tag                  = new Tag;
                        $tag->name            = $tag_input;
                        $tag->tag_category_id = $tag_category->id;
                        $tag->published       = true;
                        $tag->save();
                    }

                    // Insert posts_tag
                    $gifs_tag          = new PostsTag;
                    $gifs_tag->post_id = $gif->id;
                    $gifs_tag->tag_id  = $tag->id;

                    $gifs_tag->save();
                }
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Gif has been updated!'
        ]);
    }

    public function delete()
    {
        $gif = Post::where( 'post_type', 'gif' )->find(request('post_id'));

        if(!$gif){
            $alert = [
                'message' => 'Gif does not exists.',
                'class'   => 'alert--error',
            ];            
            return redirect()->back()->with('alert', $alert);
        }

        $gif->update(['is_deleted' => 1, 'is_published' => 0, 'is_pending' => 0, 'is_rejected' => 0, 'reject_reason' => '']);

        return redirect('admin/gifs');
    }

    public function deleteGif($gif = null)
    {
        if (!$gif) {
            return;
        }

        $description      = json_decode($gif->description);
        $blocks           = $description->blocks ?? [];

        // Delete thumbnails
        if ($gif->thumbnail) {
            Storage::delete('public/gifs/original/' . $gif->thumbnail);
        }

        if ($gif->thumbnail_medium) {
            Storage::delete('public/gifs/thumbnail' . $gif->thumbnail_medium);
        }

        // Delete records on `posts_tags` table
        $gifs_tags = $gif->postsTag;

        foreach ($gifs_tags as $gifs_tag) {
            $gifs_tag->delete();
        }

        // Finally, delete gif
        return $gif->delete();
    }

    public function deletePermanently()
    {
        $gif = Post::where( 'post_type', 'gif' )->find(request('post_id'));

        if (!$gif) {
            $alert = [
                'message' => 'Gif does not exists.',
                'class'   => 'alert--error',
            ];            
            return redirect()->back()->with('alert', $alert);
        }

        $this->deleteGif($gif);

        return redirect('admin/gifs');

    }

    public function deleteMultiple(Request $request)
    {
        $selectedIDs     = $request->input('selectedIDs');

        // if nothing is selected just return
        if ($selectedIDs == null) {
            return back();
        }
        
        Post::where( 'post_type', 'gif' )->whereIn('id', $selectedIDs)->update(['is_deleted' => 1, 'is_rejected' => 0, 'reject_reason' => '']);

        $alert = [
            'message' => 'Gifs has been deleted!',
            'class'   => '',
        ];            
        return back()->with('alert', $alert);
    }

    public function emptyTrash()
    {

        // Get gifs on trash
        $trashed_gifs = Post::where( 'post_type', 'gif' )->where('is_deleted', 1)->get();

        foreach ($trashed_gifs as $gif) {
            $this->deleteGif($gif);
        }


        return redirect('admin/gifs');
    }

    public function restore($id)
    {
        $gif = Post::where( 'post_type', 'gif' )->find($id);

        if(!$gif){
            $alert = [
                'message' => 'Gif does not exists.',
                'class'   => 'alert--error',
            ];            
            return redirect()->back()->with('alert', $alert);
        }

        $gif->update(['is_deleted' => 0, 'is_pending' => 0, 'is_published' => 0]);

        return redirect('admin/gifs');
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        return view('gif::show');
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        return view('gif::forms');
    }

    public function makeGifDraft($id) {
        $gif = Post::where( 'post_type', 'gif' )->find($id);
        if (!$gif) {
            $alert = [
                'message' => 'Gif does not exists.',
                'class'   => 'alert--error',
            ];            
            return redirect()->back()->with('alert', $alert);
        }

        $gif->update(['is_published' => 0, 'is_pending' => 0, 'is_rejected' => 0, 'reject_reason' => '']);

        return redirect('admin/gifs');
    }

    public function makeGifPublish($id) {
        $gif = Post::where( 'post_type', 'gif' )->find($id);
        if (!$gif) {
            $alert = [
                'message' => 'Gif does not exists.',
                'class'   => 'alert--error',
            ];            
            return redirect()->back()->with('alert', $alert);
        }

        $gif->update(['is_published' => 1, 'is_pending' => 0, 'is_rejected' => 0, 'reject_reason' => '']);

        return redirect('admin/gifs');
    }

    public function gifs(Request $request)
    {
        $gifs = Post::where(
            [
                'post_type'    => 'gif',
                'is_published' => true,
                'is_pending'   => false,
                'is_rejected'  => false,
                'is_deleted'   => false
            ]
        )->orderBy('created_at', 'desc');

        $gif = $gif->get();
        $page_title = 'Gifs';

        // Set view data
        $data['page_title'] = $page_title;
        $data['posts']      = $gifs;
        $data['request']    = $request;

        return view('gif::archive.post-archive', $data);
    }

    public function searches(Request $request)
    {
        $q = $request->input('q');

        $gifs = Post::where(
            [
                'post_type'    => 'gif',
                'is_published' => true,
                'is_pending'   => false,
                'is_rejected'  => false,
                'is_deleted'   => false
            ]
        )->orderBy('created_at', 'desc');

        if($request->has('q')) {
            $gifs->where('title', 'LIKE', '%' . $q . '%')
            ->orWhere('description', 'LIKE', '%' . $q . '%');
        }

        $gifs = $gifs->get();
        $page_title = $q ?? 'Gifs'; // If there's no search query -> set title to `Gifs`

        // Set view data
        $data['page_title'] = $page_title;
        $data['posts']      = $gifs;
        $data['q']          = $q;
        $data['request']    = $request;

        return view('gif::archive.search-archive', $data);
    }

    public function singleGif($slug)
    {
        $gif = Post::where( 'post_type', 'gif' )->firstWhere('slug', $slug);

        if (!$gif) {
            abort(404);
        }

        $data['post']       = $gif;
        $data['page_title'] = $gif->title;

        return view('gif::templates.gif-template', $data);
    }

    public function singleGifbyTheme($theme, $slug)
    {
        $gif = Post::where( 'post_type', 'gif' )->firstWhere('slug', $slug);

        if (!$gif) {
            abort(404);
        }

        $data['post']       = $gif;
        $data['page_title'] = $gif->title;
        $data['theme'] = $theme;

        return view('gif::templates.gif-template-v1', $data);
    }

    public function ajaxShowGifs($page_num)
    {
        // This return only 'gif' type.
        $perpage = 12;
        $offset = ($page_num - 1) * $perpage;
        $gifs = Post::leftJoin('users', 'posts.user_id', '=', 'users.id')
            ->select([
                'posts.id',
                'title',
                'slug',
                'post_type',
                'posts.created_at as created_at',
                'thumbnail',
                'thumbnail_medium',
                'users.name',
                'users.username',
                'users.avatar as avatar'
            ])->where(
                [
                    'post_type'    => 'gif',
                    'is_published' => true,
                    'is_pending'   => false,
                    'is_rejected'  => false,
                    'is_deleted'   => false
                ]    
            )
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($perpage);

        $gifs = $gifs->get();

        $gifs_count = Post::where([
            'post_type'    => 'gif',
            'is_published' => true,
            'is_pending'   => false,
            'is_rejected'  => false,
            'is_deleted'   => false
        ])->count();

        $data['total'] = $gifs_count;
        $data['posts'] = $gifs;
        $data['nextpage'] = ($gifs_count - $offset - $perpage) > 0 ? ($page_num + 1) : 0;

        return view('gif::templates.gif-masonry-load', $data);
    }

    public function ajaxInfiniteLoadGif($gif_id, $page_num) {
        // This requires to filter 'post' only.
        $tags = Post::find($gif_id)->getTagNames();

        $perpage = 1;
        $offset = ($page_num - 1) * $perpage;

        $gifs = Post::leftJoin('posts_tags', 'posts_tags.post_id', '=', 'posts.id')
            ->leftJoin('tags', 'posts_tags.tag_id', '=', 'tags.id')
            ->select([
                'posts.id',
                DB::raw('COUNT(*) as relevance')
            ])
            ->where('posts.id', '<>', $gif_id)
            ->whereIn('tags.name', $tags)
            ->where(
                [
                    'post_type'    => 'gif',
                    'is_published' => true,
                    'is_pending'   => false,
                    'is_rejected'  => false,
                    'is_deleted'   => false
                ]    
            )
            ->groupBy('posts.id')
            ->orderBy('relevance', 'desc')
            ->orderBy('posts.updated_at', 'desc')
            ->offset($offset)
            ->limit($perpage);

        $new_gif_info = $gifs->get()->first();

        $gif = null;
        if ($new_gif_info) {
            $gif = Post::find($new_gif_info['id']);

            if ($gif) {
                $gif['description'] = Post::parseContent($gif['description']);
                $gif['seo_title'] = $gif['title'] . ' | [sitetitle]';
                $gif['url'] = 'gif/' . $gif['slug'];
            }
        }

        $gifs_count = Post::leftJoin('posts_tags', 'posts_tags.post_id', '=', 'posts.id')
            ->leftJoin('tags', 'posts_tags.tag_id', '=', 'tags.id')
            ->select([
                'posts.id',
            ])
            ->where('posts.id', '<>', $gif_id)
            ->whereIn('tags.name', $tags)
            ->where(
                [
                    'post_type'    => 'gif',
                    'is_published' => true,
                    'is_pending'   => false,
                    'is_rejected'  => false,
                    'is_deleted'   => false
                ]    
            )->groupBy('posts.id');
        $gifs_count = count($gifs_count->get());

        $tag_pills = $gif ? $gif->getTagNames() : [];

        $data['total'] = $gifs_count;
        $data['gif'] = $gif;
        $data['tag_pills'] = $tag_pills;
        $data['nextpage'] = ($gifs_count - $offset - $perpage) > 0 ? ($page_num + 1) : 0;

        return view('gif::templates.gif-infinite-load', $data);
    }

    public function makeGifReject() {
        $gif = Post::where( 'post_type', 'gif' )->find($id);
        if (!$gif) {
            return response()->json([
                'status' => false,
                'message' => 'Gif does not exists!'
            ]);
    
            return redirect()->back()->with('alert', $alert);    
        }

        if (! $gif->is_pending) {
            return response()->json([
                'status' => false,
                'message' => 'The gif is not pending.'
            ]);
    
            return redirect()->back()->with('alert', $alert);    
        }

        $gif->update(['is_rejected' => 1, 'reject_reason' => request('message')]);
        
        return response()->json([
            'status' => true,
            'message' => 'Gif has been rejected.'
        ]);
    }
    
    public function getRejectedGifs()
    {
        $gifs = Post::leftJoin('users', 'posts.user_id', '=', 'users.id')
            ->select([
                'posts.id',
                'title',
                'slug',
                'posts.created_at as created_at',
                'thumbnail',
                'thumbnail_medium',
                'is_deleted',
                'is_pending',
                'is_published',
                'is_rejected',
                'reject_reason',
                'users.username as username'
            ])->orderBy('created_at', 'desc');

        if(request()->has('gifsearch')){
            $gifs->where('title', 'LIKE', '%' . request('gifsearch') . '%')
            ->orWhere('users.name', 'LIKE', '%' . request('gifsearch') . '%');
        }

        $gifs = $gifs->where( 'post_type', 'gif' )->where('is_published', 0)->where('is_pending', 1)->where('is_rejected', 1);

        $limit = request('limit') ? request('limit') : 25;

        $gifs = $gifs->paginate($limit, ['*'], 'r_page');

        return $gifs;
    }    
}