<?php

namespace App\Http\Controllers;

use App\Jobs\UpdateRepositoryDetails;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Event;
use App\Models\LaravelVersion;
use App\Models\Organization;
use App\Models\Post;
use App\Models\Repository;
use App\Models\Role;
use App\Models\Score;
use App\Models\User;
use App\Notifications\YouWereMentionedNotification;
use Carbon\Carbon;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;

class ExampleController extends Controller
{
    //show the permissions divided by the delimiter
    public function example1()
    {
        $role = Role::with('permissions')->first();
        $permissionsListToShow = $role->permissions
            ->map(fn($permission) => $permission->name)//from each permission, we need only the name
            ->implode("<br>");//adding on something

        return view('example1');
    }

    //find the key of array by items, with maximum value
    public function example11()
    {
        $hazards = [
            'BM-1' => 8,
            'LT-1' => 7,
            'LT-P1' => 6,
            'LT-UNK' => 5,
            'BM-2' => 4,
            'BM-3' => 3,
            'BM-4' => 2,
            'BM-U' => 1,
            'NoGS' => 0,
            'Not Screened' => 0,
        ];

        //collection filter by array
        $score = Score::all()
            ->map(function ($item) use ($hazards) {
                return $hazards[$item->field];
            })->max();//run through the collection and take the max

        info($score);

        return view('example11');
    }

    //extract all multi-dimensional array of one level drop
    //pluck and flatten
    public function example12()
    {
        $elements = collect(config('setting_fields'))
            ->pluck('elements')
            ->flatten(1);
        info($elements);

        return view('example12');
    }

    public function example2()
    {
        Artisan::call('twitter:giveaway', ['--exclude' => ['someuser', '@otheruser']]);

        return view('example2');
    }

    public function example2_1()
    {
        $excluded = collect($this->option('exclude'))
            ->push('povilaskorop', '@dailylaravel')//adding items to the collection
            ->map(fn (string $name): string => str_replace('@', '', $name))
            ->implode(', ');
    }

    public function example3()
    {
        $user = User::first();
        $socialLinks = collect([
            'Twitter' => $user->link_twitter,
            'Facebook' => $user->link_facebook,
            'Instagram' => $user->link_instagram,
        ])
            ->filter()//by default removes empty or null values
            ->map(fn($link, $network) => '<a href="' . $link . '">' . $network . '</a>')//the map function has value then key
            ->implode(' | ');

        return view('example3', compact('user'));
    }

    public function example4()
    {
        Repository::query()
            ->with('owner')
            ->get()
            ->reject(function (Repository $repository): bool {
                return $repository->owner instanceof User && $repository->owner->github_access_token === null;
            })
            ->reject(function (Repository $repository): bool {
                return $repository->owner instanceof Organization
                    && $repository->owner->members()
                        ->whereNotNull('github_access_token')
                        ->whereNotNull('registered_at')
                        ->doesntExist();
            })
            ->each(static function (Repository $repository): void {
                UpdateRepositoryDetails::dispatch($repository);
            });

        return view('example4');
    }

    public function example7()
    {
        $comment = Comment::first();
        collect($comment->mentionedUsers())
            ->map(function ($name) {
                return User::where('name', $name)->first();
            })
            ->filter()
            ->each(function ($user) use ($comment) {
                $user->notify(new YouWereMentionedNotification($comment));
            });

        return view('example7');
    }

    public function example9()
    {
        $locale = 'en';
        $path = Category::all()
            ->map(function ($i) use ($locale) {
                return $i->getSlug($locale);
            })
            ->filter()
            ->implode('/');
        info($path);

        return view('example9');
    }

    public function example13()
    {
        Post::all()
            ->filter->isTweet()
            ->filter(function (Post $post) {
                return empty($post->external_url);
            })
            ->each(function (Post $post) {
                preg_match('/(?=https:\/\/twitter.com\/).+?(?=")/', $post->text, $matches);

                if (count($matches) > 0) {
                    info($matches[0]);
                    $post->external_url = $matches[0];

                    // Update external_url
                    // $post->save();
                }
            });

        return view('example13');
    }

    public function example5()
    {
        $events = Event::all();
        $filteredEvents = $events
            ->unique(fn($event) => $event->message)//filter out the repeating items
            ->filter(fn($event) => !is_null($event->subject))
            ->map(fn($event) => $this->extractData($event))
            ->values();

        info($filteredEvents);

        return view('example5');
    }

    private function extractData(Event $event): array
    {
        return [
            'label' => ucwords($event->subject),
            'message' => [
                'success' => $event->status !== 'error',
                'message' => $event->message
            ]
        ];
    }

    public function example6()
    {
        // Creating 10 temporary files for the test
        $f = fopen(storage_path('logs/demo/laravel.log'), 'w');
        fclose($f);
        for ($day = 0; $day < 10; $day++) {
            $date = now()->subDays($day)->toDateString();
            $f = fopen(storage_path('logs/demo/laravel-' . $date . '.log'), 'w');
            fclose($f);
        }

        $thresholdDate = now()->subDays(3)->setTime(0, 0, 0, 0);

        $files = Storage::disk("logs")->allFiles();
        $logFiles = collect($files)
            ->mapWithKeys(function ($file) {
                $matches = [];
                $isMatch = preg_match("/^laravel\-(.*)\.log$/i", $file, $matches);

                if (count($matches) > 1) {
                    $date = $matches[1];
                }

                $key = $isMatch ? $date : "";
                return [$key => $file];
            })
            ->forget("")
            ->filter(function ($value, $key) use ($thresholdDate) {
                try {
                    $date = Carbon::parse($key);
                } catch (\Exception $e) {
                    return true;
                }

                return $date->isBefore($thresholdDate);
            });
        info($logFiles);

        return view('example6');
    }



    public function example8()
    {
        $versionsFromGithub = collect([
            ['name' => 'v9.19.0'],
            ['name' => 'v8.83.18'],
            ['name' => 'v9.18.0'],
            ['name' => 'v8.83.17'],
            ['name' => 'v9.17.0'],
            ['name' => 'v8.83.16'],
            // ...
        ]);

        $versionsFromGithub
            // Map into arrays containing major, minor, and patch numbers
            ->map(function ($item) {
                $pieces = explode('.', ltrim($item['name'], 'v'));

                return [
                    'major' => $pieces[0],
                    'minor' => $pieces[1],
                    'patch' => $pieces[2] ?? null,
                ];
            })
            // Map into groups by release; pre-6, major/minor pair; post-6, major
            ->mapToGroups(function ($item) {
                if ($item['major'] < 6) {
                    return [$item['major'] . '.' . $item['minor'] => $item];
                }

                return [$item['major'] => $item];
            })
            // Take the highest patch or minor/patch number from each release
            ->map(function ($item) {
                if ($item->first()['major'] < 6) {
                    // Take the highest patch
                    return $item->sortByDesc('patch')->first();
                }

                // Take the highest minor, then its highest patch
                return $item->sortBy([['minor', 'desc'], ['patch', 'desc']])->first();
            })
            ->each(function ($item) {
                if ($item['major'] < 6) {
                    $version = LaravelVersion::where([
                        'major' => $item['major'],
                        'minor' => $item['minor'],
                    ])->first();

                    if ($version->patch < $item['patch']) {
                        $version->update(['patch' => $item['patch']]);
                        info('Updated Laravel version ' . $version . ' to use latest patch.');
                    }
                }

                $version = LaravelVersion::where([
                    'major' => $item['major'],
                ])->first();

                if (!$version) {
                    // Create it if it doesn't exist
                    $created = LaravelVersion::create([
                        'major' => $item['major'],
                        'minor' => $item['minor'],
                        'patch' => $item['patch'],
                    ]);

                    info('Created Laravel version ' . $created);
                } // Update the minor and patch if needed
                else if ($version->minor != $item['minor'] || $version->patch != $item['patch']) {
                    $version->update(['minor' => $item['minor'], 'patch' => $item['patch']]);
                    info('Updated Laravel version ' . $version . ' to use latest minor/patch.');
                }
            });

        return view('example8');
    }



    public function example10()
    {
        $class = 'App\\Base\\Http\\Livewire\\SomeClass';
        $classNamespaces = [
            'App\\Base\\Http\\Livewire',
            'App\\Project\\Livewire'
        ];

        $classNamespace = collect($classNamespaces)->filter(fn($x) => strpos($class, $x) !== false)->first();
        $namespace = collect(explode('.', str_replace(['/', '\\'], '.', $classNamespace)))
            ->map([Str::class, 'kebab'])
            ->implode('.');
        info($namespace);

        return view('example10');
    }


    public function example14()
    {
        $classNamespaces = [
            'App\\Base\\Http\\Livewire',
            'App\\Project\\Livewire'
        ];
        $folders = collect($classNamespaces)->map(function ($namespace) {
            $name = str($namespace)->finish('\\')->replaceFirst(app()->getNamespace(), '');
            return app('path') . '/' . str_replace('\\', '/', $name);
        });

        $classNames = $folders
            ->map(function ($item) {
                return (new Filesystem())->allFiles($item);
            })
            ->flatten()
            ->map(function (\SplFileInfo $file) {
                return app()->getNamespace() . str_replace(
                        ['/', '.php'],
                        ['\\', ''],
                        Str::after($file->getPathname(), app_path() . '/')
                    );
            })
            ->filter(function (string $class) {
                return is_subclass_of($class, Component::class) &&
                    !(new \ReflectionClass($class))->isAbstract();
            });
        info($classNames);

        return view('example14');
    }

    public function example15()
    {
        $filesystem = new Filesystem();
        $plugins = [];
        $ignore = ['nesbot/carbon'];
        if ($filesystem->exists($path = base_path() . '/vendor/composer/installed.json')) {
            $plugins = json_decode($filesystem->get($path), true);
        }

        $packages = collect($plugins['packages'])
            ->mapWithKeys(function ($package) {
                return [$this->format($package['name']) => $package['extra']['laravel'] ?? []];
            })
            ->each(function ($configuration) use (&$ignore) {
                $ignore = array_merge($ignore, $configuration['dont-discover'] ?? []);
            })
            ->reject(function ($configuration, $package) use ($ignore) {
                return in_array($package, $ignore);
            })
            ->filter()
            ->all();

        info($packages);

        return view('example15');
    }

    protected function format($package)
    {
        return str_replace('vendor/', '', $package);
    }

}
