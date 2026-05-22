<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UploadFundCycleEventBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAdminAccess() ?? false;
    }

    public function rules(): array
    {
        return [
            'cover_image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'cover_image.required' => 'Please select an image file before uploading.',
            'cover_image.max' => 'Image size must be 2 MB or less on this server.',
            'cover_image.mimes' => 'Only JPG, PNG, or WEBP images are allowed.',
            'cover_image.uploaded' => 'Image upload failed at PHP level. Keep file size below server limits and try again.',
        ];
    }
}
