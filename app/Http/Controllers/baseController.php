<?php

declare(strict_types=1);

namespace App\Http\Controllers;

class baseController extends Controller
{
    // base view paths
    protected $page = 'Frontend.Pages.';

    protected $header = 'Frontend.Header';

    protected $footer = 'Frontend.Footer';

    protected $partials = 'Frontend.Partials';

    /**
     * Return a view from the Pages folder with optional data.
     *
     * @return \Illuminate\View\View
     */
    protected function view(string $view, array $data = [])
    {
        return view("{$this->page}.{$view}", $data);
    }
}
