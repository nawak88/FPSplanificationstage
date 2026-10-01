<?php

namespace Modules\FPSplanificationstage\Services;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

class PublicInscriptionPdfDownload
{
    public function __invoke(
        Request $request,
        string $code
    ): Response {
        if (
            ! URL::hasValidSignature(
                $request,
                false
            )
        ) {
            abort(403);
        }

        $inscription =
            app(
                PublicInscriptionPageService::class
            )->findByCode(
                $code
            );

        $pdf =
            app(
                InscriptionPdfService::class
            )->render(
                $inscription
            );

        $filename =
            'candidature-stage-'
            . $inscription
                ->code_inscription
            . '.pdf';

        return response(
            $pdf,
            200,
            [
                'Content-Type' =>
                    'application/pdf',

                'Content-Disposition' =>
                    'attachment; filename="'
                    . $filename
                    . '"',

                'Cache-Control' =>
                    'private, no-store, max-age=0',

                'X-Content-Type-Options' =>
                    'nosniff',
            ]
        );
    }
}
