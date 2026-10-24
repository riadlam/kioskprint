<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePrintFilesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxKb = (int) config('print.max_kilobytes');
        $maxFiles = (int) config('print.max_files');

        return [
            'locale' => ['nullable', 'in:ar,fr'],
            'files' => ['required', 'array', 'min:1', 'max:'.$maxFiles],
            'files.*' => ['file', 'max:'.$maxKb],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $locale = $this->input('locale') === 'fr' ? 'fr' : 'ar';
            $extensions = config('print.extensions');
            $mimes = config('print.mimes');

            foreach ($this->file('files', []) as $index => $file) {
                if (! $file) {
                    continue;
                }

                $ext = strtolower($file->getClientOriginalExtension());
                $mime = strtolower((string) $file->getMimeType());
                $mimeOk = in_array($mime, $mimes, true) || ($mime === 'application/octet-stream' && $ext === 'pdf');

                if (! in_array($ext, $extensions, true) || ! $mimeOk) {
                    $validator->errors()->add(
                        "files.{$index}",
                        $locale === 'ar'
                            ? 'الملفات المدعومة: PDF و JPG و PNG و WEBP فقط.'
                            : 'Fichiers acceptés : PDF, JPG, PNG et WEBP.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        $ar = $this->input('locale') !== 'fr';

        if ($ar) {
            return [
                'files.required' => 'اختر ملفاً واحداً على الأقل.',
                'files.min' => 'اختر ملفاً واحداً على الأقل.',
                'files.max' => 'عدد الملفات أكبر من المسموح.',
                'files.*.max' => 'كل ملف يجب أن يكون أصغر من 12 ميغابايت.',
                'files.*.file' => 'أحد الاختيارات ليس ملفاً صالحاً.',
            ];
        }

        return [
            'files.required' => 'Choisissez au moins un fichier.',
            'files.min' => 'Choisissez au moins un fichier.',
            'files.max' => 'Trop de fichiers.',
            'files.*.max' => 'Chaque fichier doit faire moins de 12 Mo.',
            'files.*.file' => 'Un des éléments choisis n’est pas un fichier valide.',
        ];
    }

    public function locale(): string
    {
        return $this->input('locale') === 'fr' ? 'fr' : 'ar';
    }
}
