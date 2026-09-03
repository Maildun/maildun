<?php

test('upload notifications render a per file progress panel that updates in place', function () {
    $root = dirname(__DIR__, 2).'/resources/js';
    $toaster = file_get_contents($root.'/components/ui/toast.tsx');
    $uploadToast = file_get_contents($root.'/hooks/use-upload-toast.ts');

    expect($toaster)->toBeString()
        ->toContain('kind: "upload"')
        ->toContain('aria-label="Upload progress"')
        ->toContain('{progress}%')
        ->toContain('{uploadFiles.length} total • {uploadSummary.complete}')
        ->toContain('${complete} / ${files.length} ${word}')
        // In-flight rows say "Sent", never "Done": the whole transfer is one
        // request, so nothing is stored until the response settles it.
        ->toContain('sent: { label: "Sent"')
        ->toContain('uploadStatusBadges[file.status]')
        ->toContain('visibleUploadFiles(files, UPLOAD_FILE_ROWS)');

    expect($uploadToast)->toBeString()
        ->toContain("type: 'loading'")
        ->toContain('timeout: 0')
        ->toContain("kind: 'upload'")
        ->toContain('uploadFileStates(')
        ->toContain("filePercentage >= 100 ? 'sent' : 'uploading'")
        ->toContain('toast.update(toastId.current')
        // Settling keeps the panel for transfers that listed files and falls
        // back to a plain toast for single unnamed uploads.
        ->toContain('current && current.files.length > 0')
        ->toContain('timeout: 5000');
});

test('every file upload entry point reports progress to the upload toast', function () {
    $root = dirname(__DIR__, 2).'/resources/js';
    $paths = [
        'hooks/use-media-upload.ts',
        'pages/settings/profile.tsx',
        'pages/teams/create.tsx',
        'pages/teams/edit.tsx',
        'pages/subscribe-forms/edit.tsx',
        'pages/emails/edit.tsx',
    ];

    foreach ($paths as $path) {
        $source = file_get_contents($root.'/'.$path);

        expect("{$path}\n{$source}")->toBeString()
            ->toContain('useUploadToast')
            ->toContain('onProgress')
            // The whole progress event is forwarded, not just the percentage,
            // so the toast can split the sent bytes across the queued files.
            ->toMatch('/uploadToast\.setProgress\((event|progress)\)/');
    }
});

test('multi file uploads hand the toast their file list', function () {
    $root = dirname(__DIR__, 2).'/resources/js';
    $media = file_get_contents($root.'/hooks/use-media-upload.ts');
    $attachments = file_get_contents($root.'/pages/emails/edit.tsx');

    expect($media)->toBeString()
        ->toContain('files,')
        ->toContain('hint: `Up to ${MEDIA_MAX_FILES} images, 2 MB each.`');

    expect($attachments)->toBeString()
        ->toContain('files: Array.from(files),');
});
