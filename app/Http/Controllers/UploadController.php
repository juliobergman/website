<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\UserData;
use Illuminate\Http\File;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{

    protected $maxSize = [
        'width' => '2880',
        'height' => '2880',
    ];

    protected $formats = [
        // 'Name' => ['width','height','Mantain Aspect Ratio'],
        'xlarge' => ['2048','1536', true],
        'large' => ['1024','768', true],
        'medium' => ['640','480', false],
        'thumbnail' => ['360','360', false],
    ];

    public function upload(Request $request)
    {
        $this->validate($request, [
            'image' => 'required',
            'image.*' => 'mimes:png,jpg,jpeg,svg'
        ]);

        $category = $request->category;

        if($request->hasfile('image')){
            foreach ($request->file('image') as $key => $file) {
                $original_filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $random_number = rand(1, 5000);
                $hash = hash('md5', $original_filename.time().$random_number);


                $insert[$key]['category_id'] = $category;
                $insert[$key]['public_path'] = '/public/media/'.$hash;
                $insert[$key]['storage_path'] = '/storage/media/'.$hash;



                $fullsize = Storage::putFileAs('public/media/'.$hash, new File($file), $hash.'.'.$file->extension());
                $insert[$key]['fullsize'] = Storage::url($fullsize);

                // SVG IMAGE
                if ($file->extension() == 'svg') {
                    if ($fullsize) {
                        foreach ($this->formats as $size => $atr) {
                            $name = $hash;
                            $path = storage_path('app/public/media/'.$hash.'/'.$name);
                            $insert[$key][$size] = Storage::url($fullsize);
                        }
                    }
                }


                // Image Intervention
                if ($file->extension() != 'svg') {


                    //  Resize Max
                    $img = Image::make(storage_path('app/'.$fullsize));
                    $img->resize($this->maxSize['width'], $this->maxSize['height'], function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    });
                    $img->save(storage_path('app/'.$fullsize));


                    if ($fullsize) {
                        foreach ($this->formats as $size => $atr) {
                            $ext = $file->extension();
                            $img = Image::make(storage_path('app/'.$fullsize));
                            $name = $hash.'-'.$size.'.'.$ext;
                            $path = storage_path('app/public/media/'.$hash.'/'.$name);
                            if (!$atr[2]) {
                                $img->fit($atr[0], $atr[1], function ($constraint) {
                                    $constraint->upsize();
                                });
                            }
                            if ($atr[2]) {
                                $img->resize(null, $atr[0], function ($constraint) {
                                    $constraint->aspectRatio();
                                    $constraint->upsize();
                                });
                            }
                            $img->save($path, 90, $ext);
                            $insert[$key][$size] = '/storage/media/'.$hash.'/'.$name;
                        }
                    }
                }

            }

            Media::insert($insert);
        }

        return new JsonResponse(['message' => 'Success', 'category' => 'Category: '.$category], 200);
    }

    public function replace(Request $request)
    {

        $this->validate($request, [
            'image' => 'required',
            'image.*' => 'mimes:png,jpg,jpeg'
        ]);
        $file = $request->file('image');

        if(!$file){
            return new JsonResponse(['message' => 'No File'], 404);
        }

        $id = $request->id;
        $media = Media::where('id', $id)->first();
        if(!$media){
            return new JsonResponse(['message' => 'Item not Found'], 404);
        }

        // Delete Previous
        $path = $media->public_path;
        if(!Storage::deleteDirectory($path)){
            return new JsonResponse(['message' => 'Error'], 405);
        }

        // Upload New
        $original_filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $random_number = rand(1,5000);
        $hash = hash('md5', $original_filename.time().$random_number);

        $update['public_path'] = '/public/media/'.$hash;
        $update['storage_path'] = '/storage/media/'.$hash;

        $fullsize = Storage::putFileAs('public/media/'.$hash, new File($file), $hash.'.'.$file->extension());
        $update['fullsize'] = Storage::url($fullsize);

        // SVG IMAGE
        if ($file->extension() == 'svg') {
            if ($fullsize) {
                foreach ($this->formats as $size => $atr) {
                    $name = $hash;
                    $path = storage_path('app/public/media/'.$hash.'/'.$name);
                    $update[$size] = Storage::url($fullsize);
                }
            }
        }

        // Image Intervention
        if ($file->extension() != 'svg') {

            //  Resize Max
            $img = Image::make(storage_path('app/'.$fullsize));
            $img->resize($this->maxSize['width'], $this->maxSize['height'], function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            $img->save(storage_path('app/'.$fullsize));

            if ($fullsize) {
                foreach ($this->formats as $size => $atr) {
                    $ext = $file->extension();
                    $img = Image::make(storage_path('app/'.$fullsize));
                    $name = $hash.'-'.$size.'.'.$ext;
                    $path = storage_path('app/public/media/'.$hash.'/'.$name);
                    if (!$atr[2]) {
                        $img->fit($atr[0], $atr[1], function ($constraint) {
                            $constraint->upsize();
                        });
                    }
                    if ($atr[2]) {
                        $img->resize(null, $atr[0], function ($constraint) {
                            $constraint->aspectRatio();
                            $constraint->upsize();
                        });
                    }
                    $img->save($path, 90, $ext);
                    $update[$size] = '/storage/media/'.$hash.'/'.$name;
                }
            }
        }

        Media::where('id', $id)->update($update);
        return Media::where('id', $id)->first();

    }

    public function avatar(Request $request)
    {
        // Validation
        $this->validate($request, [
            'image' => 'required',
            'image.*' => 'mimes:png,jpg,jpeg'
        ]);
        $file = $request->file('image');
        if(!$file){
            return new JsonResponse(['message' => 'No File'], 200);
        }
        $user_id = $request->user()->id;
        // $user_id = 1;

        // Create Directory if not Exists
        if(!file_exists(storage_path('app/public/avatars/'.$user_id.'/'))) {
            Storage::makeDirectory('/public/avatars/'.$user_id); //creates directory
        }

        // Delete Old Avatar
        $oldpath = UserData::select('profile_pic')->where('user_id', $user_id)->first();
        $deletepath = str_replace('/storage/', '/public/', $oldpath->profile_pic);
        $factory = strstr($deletepath, '/factory/');
        if(!$factory){
            Storage::delete($deletepath);
        };
        // Upload New Avatar
        $original_filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $random_number = rand(1,5000);
        $hash = hash('md5', $original_filename.time().$random_number);

        $ext = 'jpg';
        $img = Image::make($file->getRealPath());
        $name = $hash.'.'.$ext;
        $path = storage_path('app/public/avatars/'.$user_id.'/'.$name);
        $img->fit(800, 800, function ($constraint) {
            $constraint->upsize();
        });
        $img->save($path, 60, $ext);
        $newpath = '/storage/avatars/'.$user_id.'/'.$name;
        $update = array('profile_pic' => $newpath);
        UserData::where('user_id', $user_id)->update($update);
        // Response
        return new JsonResponse(['message' => 'Success', 'path' => $newpath], 200);

    }

    public function destroy(Request $request)
    {
        $id = $request->id;

        $mediaQuery = Media::query();
        $mediaQuery->where('id', $id);
        $mediaQuery->with('cover');
        $media = $mediaQuery->first();

        if(!$media){
            return new JsonResponse(['errors' => ['Item not Found']], 404);
        }

        $cover = count($media->cover);
        if($cover){
            return new JsonResponse(['errors' => ['Delete Fail','This Image is used as cover']], 405);
        }

        $path = $media->public_path;
        if($media->delete()){
            if(Storage::deleteDirectory($path)){
                return new JsonResponse(['messages' => ['Media Successfully Deleted']], 200);
            }
        }
    }

    public function destroy_bulk(Request $request)
    {
        $mediaQuery = Media::query();
        $mediaQuery->whereIn('id', $request);
        $mediaQuery->with('cover');
        $media = $mediaQuery->get();
        if(!$media){
            return new JsonResponse(['errors' => ['Item not Found']], 404);
        }

        $hasCover = false;

        foreach ($media as $key => $item) {
            $path = $item->public_path;
            $del = $item->id;
            $name = $item->name;

            $cover = count($item->cover);

            if($cover){
                $return[$key] = [
                    'id' => $del,
                    'status' => 'fail',
                    'name' => $name,
                    'message' => 'This Image is used as cover'
                ];
                $hasCover = true;
            }

            if(!$cover){
                if($item->delete()){
                    if(Storage::deleteDirectory($path)){
                        $return[$key] = [
                            'id' => $del,
                            'status' => 'success',
                            'name' => $name,
                            'message' => 'Image Successfully Deleted.'
                        ];
                    }
                }
            }
        }

        if($hasCover){
            return new JsonResponse(['messages' => ['Some files could not be deleted'], 'deleted' => $return], 202);
        }
        if(!$hasCover){
            return new JsonResponse(['messages' => ['Media Successfully Deleted'], 'deleted' => $return], 200);
        }


    }

}
