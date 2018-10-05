#!/usr/bin/php
<?php
/**
 * tweets.php - CLI script for manipulating an un-zipped twitter full backup data dump
 * relies on command-line tools, tested on MacOS.  To view the grailbird output files use
 * https://github.com/vijinho/tweets-gb
 *
 * @author Vijay Mahrra <vijay@yoyo.org>
 * @copyright (c) Copyright 2018 Vijay Mahrra
 * @license GPLv3 (http://www.gnu.org/licenses/gpl-3.0.html)
 */
ini_set('default_charset', 'utf-8');
ini_set('mbstring.encoding_translation', 'On');
ini_set('mbstring.func_overload', 6);

//-----------------------------------------------------------------------------
// required commands check
$requirements = [
    'find'    => 'cli command: find',
    'grep'    => 'cli command: grep',
    'cut'     => 'cli command: cut',
    'xargs'   => 'cli command: xargs',
    'gunzip'  => 'cli command: gunzip',
    'convert' => 'tool: convert - https://imagemagick.org/script/convert.php',
    'curl'    => 'tool: curl - https://curl.haxx.se',
    'wget'    => 'tool: wget - https://www.gnu.org/software/wget/',
];

$commands = get_commands($requirements);

if (empty($commands)) {
    verbose("Error: Missing commands.", $commands);
    exit;
}

//-----------------------------------------------------------------------------
// define command-line options
// see https://secure.php.net/manual/en/function.getopt.php
// : - required, :: - optional

$options = getopt("hvdtf:g:i:auolxr:k:",
    [
    'help',
    'verbose',
    'debug',
    'test',
    'dir:',
    'dir-output:',
    'format:',
    'filename:',
    'grailbird:',
    'grailbird-import:',
    'list',
    'list-js',
    'list-images',
    'list-videos',
    'list-users',
    'list-missing-media',
    'organize-media',
    'download-missing-media',
    'list-profile-images',
    'download-profile-images',
    'tweets-file:',
    'tweets-count',
    'tweets-all',
    'date-from:',
    'date-to:',
    'regexp:',
    'regexp-save:',
    'no-retweets',
    'no-mentions',
    'urls-expand',
    'urls-resolve',
    'urls-check',
    'urls-check-force',
    'offline',
    'local',
    'delete',
    'dupes',
    'keys-required:',
    'keys-remove:',
    'keys-filter:'
    ]);

$do = [];
foreach ([
'verbose'                 => ['v', 'verbose'],
 'test'                    => ['t', 'test'],
 'debug'                   => ['d', 'debug'],
 'test'                    => ['t', 'test'],
 'grailbird'               => ['g', 'grailbird'],
 'grailbird-import'        => [null, 'grailbird-import'],
 'list'                    => [null, 'list'],
 'list-js'                 => [null, 'list-js'],
 'list-images'             => [null, 'list-images'],
 'list-videos'             => [null, 'list-videos'],
 'list-users'              => [null, 'list-users'],
 'list-missing-media'      => [null, 'list-missing-media'],
 'organize-media'          => [null, 'organize-media'],
 'download-missing-media'  => [null, 'download-missing-media'],
 'list-profile-images'     => [null, 'list-profile-images'],
 'download-profile-images' => [null, 'download-profile-images'],
 'tweets-count'            => [null, 'tweets-count'],
 'tweets-all'              => ['a', 'tweets-all'],
 'no-retweets'             => [null, 'no-retweets'],
 'no-mentions'             => [null, 'no-mentions'],
 'urls-expand'             => [null, 'urls-expand'],
 'urls-resolve'            => ['u', 'urls-resolve'],
 'urls-check'              => [null, 'urls-check'],
 'urls-check-force'        => [null, 'urls-check-force'],
 'offline'                 => ['o', 'offline'],
 'local'                   => ['l', 'local'],
 'unlink'                  => ['x', 'delete'],
 'dupes'                   => [null, 'dupes'],
 'keys-required'           => [null, 'keys-required'],
 'keys-remove'             => ['r', 'keys-remove'],
 'keys-filter'             => ['k', 'keys-filter'],
] as $i => $opts) {
    $do[$i] = (int) (array_key_exists($opts[0], $options) || array_key_exists($opts[1],
            $options));
}
if (array_key_exists('debug', $do) && !empty($do['debug'])) {
    $do['verbose'] = $options['verbose'] = 1;
}
if (array_key_exists('urls-check-force', $options)) {
    $do['urls-check'] = $options['urls-check'] = 1;
}
if (array_key_exists('urls-check', $options)) {
    $do['urls-resolve'] = $options['urls-resolve'] = 1;
}
if (array_key_exists('urls-resolve', $options)) {
    $do['urls-expand'] = $options['urls-expand'] = 1;
}
if (array_key_exists('list-missing-media', $do) || array_key_exists('organize-media',
        $do)) {
    $do['local']      = $options['local'] = 1;
    $do['tweets-all'] = $options['tweets-all'] = 1;
}
ksort($do);

//-----------------------------------------------------------------------------
// defines (int) - forces 0 or 1 value

define('DEBUG', (int) $do['debug']);
define('VERBOSE', (int) $do['verbose']);
define('TEST', (int) $do['test']);
define('UNLINK', (int) $do['unlink']);
define('OFFLINE', (int) $do['offline']);
debug("COMMANDS:", $commands);
debug('OPTIONS:', $do);

//-----------------------------------------------------------------------------
// help
if (empty($options) || array_key_exists('h', $options) || array_key_exists('help',
        $options)) {
    options:

    $readme_file = 'README.md';
    if (file_exists($readme_file)) {
        $readme = file_get_contents('README.md');
        if (!empty($readme)) {
            output($readme . "\n");
        }
    }

    print "Requirements:\n";
    foreach ($requirements as $cmd => $desc) {
        printf("%s:\n\t%s\n", $cmd, $desc);
    }

    print join("\n",
            [
            "\nUsage: php resolve.php -u <URL>",
            "\n\tUnshorten a URL, returning the text, CURL error code or -22 (wget failure) if other URL failure\n",
            "Usage: php tweets.php",
            "Adds/Modifies/Removes/Views tweets from exported twitter archive. The modified tweet text is a new attribute: text",
            "(Specifying any other unknown argument options will be ignored.)\n",
            "\t-h,  --help                   Display this help and exit",
            "\t-v,  --verbose                Run in verbose mode",
            "\t-d,  --debug                  Run in debug mode (implies also -v, --verbose)",
            "\t-t,  --test                   Run in test mode, show what would be done, NO filesystem changes.",
            "\t     --dir={.}                Directory of unzipped twitter backup files (current dir if not specified)",
            "\t     --dir-output={.}         Directory to output files in (default to -dir above)",
            "\t     --format={json}          Output format for script data: txt|php|json (default)",
            "\t-f,  --filename={output.}     Filename for output data from operation, default is 'output.{--OUTPUT_FORMAT}'",
            "\t-g,  --grailbird={dir}        Generate json output files compatible with the standard twitter export feature to dir",
            "\t     --grailbird-import={dir} Import in data from the grailbird json files of the standard twitter export. If specified with '-a' will merge into existing tweets before outputting new file.",
            "\t     --list                   Only list all files in export folder and halt - filename",
            "\t     --list-js                Only List all javascript files in export folder and halt",
            "\t     --list-images            Only list all image files in export folder and halt",
            "\t     --list-videos            Only list all video files in export folder and halt",
            "\t     --list-users             Only list all users in tweets, (default filename 'users.json') and halt",
            "\t     --list-missing-media     List media URLs for which no local file exists and halt (implies --local)",
            "\t     --organize-media         Organize local downloaded media, for example split folder into date/month subfolders",
            "\t     --download-missing-media Download missing media (from --list-missing-media) and halt, e.g.. missing media files (implies --local)",
            "\t     --list-profile-images    Only list users profile images, (in filename 'users.json') and halt",
            "\t     --download-profile-images  WARNING: This can be a lot of users! Download profile images.",
            "\t     --tweets-count           Only show the total number of tweets and halt",
            "\t-i,  --tweets-file={tweet.js} Load tweets from different json input file instead of default twitter 'tweet.js'",
            "\t-a,  --tweets-all             Get all tweets (further operations below will depend on this)",
            "\t     --date-from              Filter tweets from date/time, see: https://secure.php.net/manual/en/function.strtotime.php",
            "\t     --date-to                Filter tweets up-to date/time, see: https://secure.php.net/manual/en/function.strtotime.php ",
            "\t     --no-retweets            Drop re-tweets (RT's)",
            "\t     --no-mentions            Drop tweets starting with mentions",
            "\t     --urls-expand            Expand URLs where shortened and data available (offline) in tweet (new attribute: text)",
            "\t-u,  --urls-resolve           Unshorten and dereference URLs in tweet (in new attribute: text) - implies --urls-expand",
            "\t     --urls-check             Check every single target url (except for twitter.com and youtube.com) and update - implies --urls-resolve",
            "\t     --urls-check-force       Forcibly checks every single failed (numeric) source and target url and update - implies --urls-check",
            "\t-o,  --offline                Do not go-online when performing tasks (only use local files for url resolution for example)",
            "\t-l,  --local                  Fetch local file information (if available) (new attributes: images,videos,files)",
            "\t-x,  --delete                 DANGER! At own risk. Delete files where savings can occur (i.e. low-res videos of same video), run with -t to test only and show files",
            "\t     --dupes                  List (or delete) duplicate files. Requires '-x/--delete' option to delete (will rename duplicated file from '{tweet_id}-{id}.{ext}' to '{id}.{ext}). Preview with '--test'!",
            "\t-r,  --keys-required=k1,k2,.  Returned tweets which MUST have all of the specified keys",
            "\t-r,  --keys-remove=k1,k2,.    List of keys to remove from tweets, comma-separated (e.g. 'sizes,lang,source,id_str')",
            "\t-k,  --keys-filter=k1,k2,.    List of keys to only show in output - comma, separated (e.g. id,created_at,text)",
            "\t     --regexp='/<pattern>/i'  Filter tweet text on regular expression, i.e /(google)/i see https://secure.php.net/manual/en/function.preg-match.php",
            "\t     --regexp-save=name       Save --regexp results in the tweet under the key 'regexps' using the key/id name given",
            "\nExamples:",
            "Report duplicate tweet media files and output to 'dupes.json':\n\tphp tweets-cli/tweets.php -fdupes.json --dupes",
            "Show total tweets in tweets file:\n\tphp tweets.php --tweets-count --verbose",
            "Write all users mentioned in tweets to file 'users.json':\n\tphp tweets.php --list-users --verbose",
            "Show javascript files in backup folder:\n\tphp tweets.php --list-js --verbose",
            "Resolve all URLs in 'tweet.js' file, writing output to 'tweet.json':\n\tphp tweets.php --tweets-all --urls-resolve --filename=tweet.json",
            "Resolve all URLs in 'tweet.js' file, writing output to grailbird files in 'grailbird' folder and also 'tweet.json':\n\tphp tweets.php --tweets-all --urls-resolve --filename=tweet.json --grailbird=grailbird",
            "Get tweets, only id, created and text keys:\n\tphp tweets.php -v -a -o -u --keys-filter=id,created_at,text",
            "Get tweets from 1 Jan 2017 to 'last friday':\n\tphp tweets.php -v -a -o -u --date-from '2017-01-01' --date-to='last friday'",
            "Filter tweet text on word 'hegemony' since last year\n\t php tweets.php -v -a -o -u -l -x -ggrailbird --date-from='last year' --regexp='/(hegemony)/i' --regexp-save=hegemony",
            "Generate grailbird files with expanded/resolved URLs:\n\tphp tweets.php --tweets-all --verbose --urls-expand --urls-resolve --grailbird=grailbird",
            "Generate grailbird files with expanded/resolved URLs using offline saved url data - no fresh checking:\n\tphp tweets.php --tweets-all --verbose --offline --urls-expand --urls-resolve --grailbird=grailbird",
            "Generate grailbird files with expanded/resolved URLs using offline saved url data and using local file references where possible:\n\tphp tweets.php --tweets-all --verbose --offline --urls-expand --urls-resolve --local --grailbird=grailbird",
            "Generate grailbird files with expanded/resolved URLs using offline saved url data and using local file references, dropping retweets:\n\tphp tweets.php --tweets-all --verbose --offline --urls-expand --urls-resolve --local --no-retweets --grailbird=grailbird",
            "Delete duplicate tweet media files (will rename them from '{tweet_id}-{id}.{ext}' to '{id}.{ext})':\n\tphp tweets-cli/tweets.php --delete --dupes",
            "Extract the first couple of words of the tweet and name the saved regexp 'words':\n\ttweets.php -v -a -o -u -l -x -ggrailbird --date-from='last year' --regexp='/^(?P<first>[a-zA-Z]+)\s+(?P<second>[a-zA-Z]+)/i' --regexp-save=words",
            "Import grailbird files from 'import/data/js/tweets':\n\tphp tweets.php --grailbird-import=import/data/js/tweets --verbose",
            "Import and merge grailbird files from 'import/data/js/tweets', fully-resolving links and local files:\n\tphp tweets-cli/tweets.php -a --grailbird=grailbird --grailbird-import=import/data/js/tweets -o -l -u --verbose",
            "List URLs for which there are missing local media files:\n\tphp tweets.php --list-missing-media --verbose",
            "Download files from URLs for which there are missing local media files:\n\tphp tweets.php -a --download-missing-media --verbose",
            "Organize 'tweet_media' folder into year/month subfolders:\n\tphp tweets-cli/tweets.php --organize-media`",
            "Export only tweets which have the 'withheld_in_countries' key to export/grailbird folder:\n\tphp tweets-cli/tweets.php -d -a -u -o -itweet.json --grailbird=export/grailbird --keys-required='withheld_in_countries'"
        ]) . "\n";

    // goto jump here if there's a problem
    errors:
    if (!empty($errors)) {
        if (is_array($errors)) {
            output("Error(s):\n\t- " . join("\n\t- ", $errors) . "\n");
        } else {
            print_r($errors);
            exit;
        }
    } else {
        output("No errors occurred.\n");
    }
    exit;
}

//-----------------------------------------------------------------------------
// url manipulation and handling variables

$url_shorteners = [// dereference & update URLs if moved or using shortener which is not twitters
    '53eig.ht', 'aca.st', 'amzn.to', 'b-o-e.uk', 'b0x.ee', 'bankofeng.uk',
    'bbc.in', 'bit.ly',
    'bitly.com', 'bloom.bg', 'boe.uk', 'bru.gl', 'buff.ly', 'cnb.cx', 'cnnmon.ie',
    'dailym.ai',
    'deck.ly', 'dld.bz',
    'dlvr.it', 'econ.st', 'eff.org', 'eurone.ws', 'fal.cn', 'fb.me', 'for.tn', 'go.nasa.gov',
    'go.shr.lc',
    'goo.gl', 'ht.ly', 'hubs.ly', 'huff.to', 'ind.pn', 'instagr.am',
    'interc.pt',
    'j.mp', 'jrnl.ie', 'jtim.es', 'kurl.nl', 'ln.is',
    'n.mynews.ly', 'newsl.it', 'n.pr',
    'nyp.st', 'nyti.ms', 'on.fb.me', 'on.ft.com', 'on.mktw.net', 'on.rt.com', 'on.wsj.com',
    'ow.ly', 'owl.li',
    'po.st', 'poal.me', 'ptv.io', 'read.bi', 'reut.rs', 'rviv.ly', 'sc.mp', 'scl.io',
    'shr.gs', 'shar.es',
    'socsi.in', 'spon.de',
    'spoti.fi', 'spr.ly', 'sptnkne.ws', 'str.sg', 't.co', 'tgam.ca', 'ti.me', 'tinurl.us',
    'tinyurl.com',
    'tlsur.net', 'tmblr.co', 'tr.im', 'trib.al', 'tws.io', 'vrge.co', 'wapo.st',
    'wef.ch', 'wp.me',
    'wpo.st', 'wrd.cm', 'wrld.bg', 'www.goo.gl', 'xhne.ws', 'yhoo.it', 'youtu.be'
];


/*
 * Bad shorteners, trouble resolving:
 *  amzn.com
 *  gu.com
 *  is.gd
 *  lnkd.in
 *  min.ie
 */

// expired domains or domains we do not want to follow
$hosts_expired = [
    'b0x.ee', '4sq.com', 'vid.me',
];

// return codes from curl (url_resolve() function below) which indiciate we should not try to resolve a url
// -22 signifies a wget failure, the rest are from curl
// https://ec.haxx.se/usingcurl-returns.html
$curl_errors_dead = [3, 6, 7, 18, 28, 35, 47, 52, 56, -22];

//-----------------------------------------------------------------------------
// initialise variables

$errors             = []; // errors to be output if a problem occurred
$output             = []; // data to be output at the end
$save_every         = OFFLINE ? 500 : 125; // save results every so often when looping, e.g. urls checked online
$online_sleep_under = OFFLINE ? 0 : 0.3; // sleep if under this many seconds elapsed performing online operation
$online_sleep       = OFFLINE ? 0 : 0.1; // time to wait between each online operation

debug("save_every: " . $save_every);
debug("online_sleep_under: " . $online_sleep_under);
debug("online_sleep: " . $online_sleep);

$tweets        = [];
$tweets_count  = 0;
$missing_media = []; // missing local media files, [filename => source url]
//-----------------------------------------------------------------------------
// set the script output format to one of (json, php, text)

$format = '';
if (!empty($options['format'])) {
    $format = $options['format'];
}
switch ($format) {
    case 'txt':
    case 'php':
        break;
    default:
    case 'json':
        $format = 'json';
}
define('OUTPUT_FORMAT', $format);
verbose(sprintf("OUTPUT_FORMAT: %s", $format));

//-----------------------------------------------------------------------------
// get dir to read unzipped twitter backup archive files from

$dir = '';
if (!empty($options['dir'])) {
    $dir = $options['dir'];
}

$dir = realpath($dir);
if (empty($dir) || !is_dir($dir)) {
    $errors[] = "You must specify a valid directory!";
    goto errors;
}

verbose(sprintf("TWEET DIR: %s", $dir));

//-----------------------------------------------------------------------------
// directory for outputting script files and data

$output_dir = '';
if (!empty($options['dir-output'])) {
    $output_dir = $options['dir-output'];
} else {
    $output_dir = $dir;
}

if (!empty($output_dir)) {
    $output_dir = realpath($output_dir);
}
if (empty($output_dir) || !is_dir($output_dir)) {
    $errors[] = "You must specify a valid output directory!";
    goto errors;
}

verbose(sprintf("OUTPUT DIR: %s", $output_dir));

//-----------------------------------------------------------------------------
// tweets data filename

if (!empty($options['i'])) {
    $tweets_file = $options['i'];
} elseif (!empty($options['tweets-file'])) {
    $tweets_file = $options['tweets-file'];
} else {
    $tweets_file = 'tweet.js';
}

verbose(sprintf("TWEETS FILENAME: %s", $tweets_file));

//-----------------------------------------------------------------------------
// output data filename

$output_filename = '';
if (!empty($options['f'])) {
    $output_filename = $options['f'];
} elseif (!empty($options['filename'])) {
    $output_filename = $options['filename'];
}

if (!empty($output_filename)) {
    verbose(sprintf("OUTPUT FILENAME: %s", $output_filename));
}

//-----------------------------------------------------------------------------
// users data filename

if ($do['list-users']) {
    $users_filename = empty($output_filename) ? 'users.json' : $output_filename;
} else {
    $users_filename = 'users.json';
}

//-----------------------------------------------------------------------------
// get date from/to from command-line

if (!empty($options['date-from'])) {
    $date_from = $options['date-from'];
}
if (!empty($date_from)) {
    $date_from = strtotime($date_from);
    if (false === $date_from) {
        $errors[] = sprintf("Unable to parse --date-from: %s",
            $options['date-from']);
    }
    verbose(sprintf("Filtering tweets FROM date/time '%s': %s",
            $options['date-from'], date('r', $date_from)));
}

if (!empty($options['date-to'])) {
    $date_to = $options['date-to'];
}
if (!empty($date_to)) {
    $date_to = strtotime($date_to);
    if (false === $date_to) {
        $errors[] = sprintf("Unable to parse --date-to: %s", $options['date-to']);
    }
    verbose(sprintf("Filtering tweets TO date/time '%s': %s",
            $options['date-to'], date('r', $date_to)));
}

//-----------------------------------------------------------------------------
// get regexp

if (!empty($options['regexp'])) {
    $regexp = $options['regexp'];
}
if (!empty($regexp)) {
    if (false === preg_match($regexp, null)) {
        $errors[] = sprintf("Unable to validate regular expression: %s",
            $options['regexp']);
    }
    verbose(sprintf("Filtering tweets with regular expression '%s'",
            $options['regexp']));
}
$regexp_save = array_key_exists('regexp-save', $options) ? $options['regexp-save']
        : false;

if (!empty($errors)) {
    goto errors;
}

//-----------------------------------------------------------------------------
// pre-fetch all files in advance if a list command-line option was specified
if ($do['list'] || $do['local'] || $do['dupes']) {
    debug('Pre-fetching files list from: ' . $dir);
    $files = files_list($dir);
    if (empty($files)) {
        $errors[] = "No files found!";
        goto errors;
    }
} else {
    $files = [];
}

if ($do['list-images'] || $do['local']) {
    verbose('Fetching images list…');

    $images = files_images($dir);
    if ($do['list-images']) {
        debug('Image files:', $images);
        $output = $images;
        goto output;
    }
}

if ($do['list-videos'] || $do['local']) {
    verbose('Fetching videos list…');
    $videos = files_videos($dir);
    if ($do['list-videos']) {
        debug('Video files:', $videos);
        $output = $videos;
        goto output;
    }
}

if ($do['list-js'] || $do['local']) {
    verbose('Fetching js list…');

    $js = files_js($dir);
    if ($do['list-js']) {
        debug('Javascript files:', $js);
        $output = $js;
        goto output;
    }
}

//-----------------------------------------------------------------------------
// prepare arrays for file list data
// $files, $images, $videos, $js and append to $output
if ($do['list']) {
    verbose('Listing files…');
    debug('Files:', $files);
    $output = $files;
    goto output;
}

//-----------------------------------------------------------------------------
// delete duplicates
if ($do['dupes']) {

    verbose("Finding duplicate files...");

    // create file keys index of key => paths
    $keys = [];
    foreach ($files as $file => $path) {
        // split on - because filename is {tweet_id}-{media_id}.{ext}
        if (!preg_match("/(?P<tweet_id>^[\d]+)-(?P<key>[^\.]+)\.(?P<ext>.+)/",
                $file, $parts)) {
            continue;
        }
        $key = $parts['key']; // e.g. EYt4vLLw.jpg
        if (array_key_exists($key, $keys)) {
            verbose(sprintf("Duplicate file found: %s\n\t%s\n\t%s", $key,
                    $keys[$key][0], $path));
        }
        $keys[$key][] = $path;
    }

    // filter file keys to remove where only 1 match occurred for the key
    foreach ($keys as $key => $paths) {
        // skip where the file only occurred once
        if (1 === count($paths)) {
            unset($keys[$key]);
            continue;
        }
    }

    if (empty($keys)) {
        verbose("No duplicate files found.");
        goto output;
    }

    verbose(sprintf("Files duplicated: %d", count($keys)));

    // go to end if no --delete specified
    $output = $keys;
    if (!UNLINK) {
        goto output;
    }

    // we are going to delete unless used with --test
    if (TEST) {
        verbose("TEST: No files will actually be deleted!");
    }

    $deletes = [];

    // find deletable non tweets_media files
    foreach ($keys as $filename => $paths) {
        foreach ($paths as $p => $path) {
            // delete the 'direct_message_media' and 'moments_tweets_media' dupe files first but not 'media_tweets'
            if (false !== stristr($path, '/direct_message_media/') ||
                false !== stristr($path, '/moments_tweets_media/')) {
                $deletes[] = $path;
                unset($paths[$p]);
            }
        }
        sort($paths); // need to do this to reset the index numbering to 0, 1, 2...
        $keys[$filename] = $paths;
    }

    // find all other duplicated files to delete and also rename
    $renames = [];
    foreach ($keys as $key => $paths) {
        if (1 === count($paths)) {
            // we only have 1 file left for the key, so we keep it
            // rename the file now to {id}.{ext}
            $renames[$paths[0]] = stristr($paths[0], $key);
            continue;
        }

        // keep the first file
        $renames[$paths[0]] = stristr($paths[0], $key);
        unset($paths[0]); // remove first element
        if (empty($paths)) {
            continue;
        }

        // all other files for the key can be left can be deleted
        foreach ($paths as $p => $path) {
            $deletes[] = $path;
        }
        unset($keys[$key]);
    }

    ksort($renames);
    if (DEBUG) {
        debug(sprintf("Files to rename: %d", count($renames)), $renames);
    } else {
        verbose(sprintf("Files to rename: %d", count($renames)));
    }
    foreach ($renames as $from => $to) {
        // prepend path of $from file to $to before renaming
        $to = substr($from, 0, strrpos($from, '/') + 1) . $to;
        if (TEST) {
            verbose("Renaming (NOT!): $from => $to");
        } else {
            verbose("Renaming: $from => $to");
            if (!rename($from, $to)) {
                $errors[] = "Error renaming file: $from => $to";
            }
        }
    }

    ksort($deletes);
    if (DEBUG) {
        debug(sprintf("Files to delete: %d", count($deletes)), $deletes);
    } else {
        verbose(sprintf("Files to delete: %d", count($deletes)));
    }
    foreach ($deletes as $path) {
        if (TEST) {
            verbose('Deleting (NOT!): ' . $path);
        } else if (UNLINK) {
            verbose('Deleting: ' . $path);
            if (!unlink($path)) {
                $errors[] = "Error deleting file: $path";
            }
        }
    }

    if (empty($errors)) {
        goto errors;
    }

    $output = [];
    goto output;
}

//-----------------------------------------------------------------------------
// return total number of tweets

$tweets       = [];
$tweets_count = 0;

if ($do['tweets-count']) {
    verbose('Counting tweets…');

    $tweets_count = tweets_count($dir);
    $output       = [$tweets_count];
    verbose("Tweets Count: $tweets_count");
    goto output;
}

//-----------------------------------------------------------------------------
// fetch tweets - all

if ($do['tweets-all'] || $do['list-users']) {
    // load in all tweets
    verbose(sprintf("Loading tweets from '%s'", $tweets_file));

    $tweets = json_load_twitter($dir, $tweets_file);
    if (empty($tweets) || is_string($tweets)) {
        $errors[] = 'No tweets found!';
        if (is_string($tweets)) {
            $errors[] = 'JSON Error: ' . $tweets;
        }
        goto errors;
    }

    $tweets_count = count($tweets);
    verbose(sprintf("Tweets loaded: %d", $tweets_count));

    verbose("Indexing loaded tweets…");
    $tweets = array_column($tweets, null, 'id'); // re-index
}


//-----------------------------------------------------------------------------
// directory for grailbird output

if ($do['grailbird']) {
    if (!empty($options['g'])) {
        $grailbird_dir = $options['g'];
    } elseif (!empty($options['grailbird'])) {
        $grailbird_dir = $options['grailbird'];
    }

    if (empty($grailbird_dir) || !is_dir($grailbird_dir)) {
        $errors[] = "You must specify a valid grailbird output directory!";
        goto errors;
    }
    $grailbird_dir = realpath($grailbird_dir);

    verbose(sprintf("GRAILBIRD OUTPUT DIR: %s", $grailbird_dir));
}


//-----------------------------------------------------------------------------
// get directory for importing grailbird data and js files there-in

if ($do['grailbird-import']) {

    $grailbird_import_dir = '';
    if (!empty($options['grailbird-import'])) {
        $grailbird_import_dir = $options['grailbird-import'];
    } else {
        $grailbird_import_dir = $dir . '/import/data/js/tweets';
    }

    $grailbird_import_dir = realpath($grailbird_import_dir);
    if (empty($grailbird_import_dir) || !is_dir($grailbird_import_dir)) {
        $errors[] = "You must specify a valid grailbird import directory!";
        goto errors;
    }
    verbose(sprintf("GRAILBIRD IMPORT DIR: %s", $grailbird_import_dir));

    $grailbird_files = files_js($grailbird_import_dir);
    if (empty($grailbird_files)) {
        $errors[] = sprintf("No grailbird js files found to import in: %s!",
            $grailbird_import_dir);
        goto errors;
    }
    if (!empty($grailbird_files) && is_array($grailbird_files)) {
        ksort($grailbird_files);
    } else {
        $grailbird_files = [];
    }
}


//-----------------------------------------------------------------------------
// get directory for importing grailbird data and js files there-in

if ($do['grailbird-import'] && !empty($grailbird_files) && is_array($grailbird_files)) {

    debug(sprintf("Importing tweets from '%s'", $grailbird_import_dir),
        $grailbird_files);

    if (empty($tweets) || !is_array($tweets)) {
        $tweets = [];
    }

    foreach ($grailbird_files as $f) {
        $filename = basename($f);
        if (!preg_match('/^[\d]{4}[_]\d\d\.js/', $filename, $matches)) {
            continue;
        }
        $data = json_load_twitter($grailbird_import_dir, $filename);
        if (!is_array($data)) {
            $errors = sprintf("No data found in file: %s", $f);
            goto errors;
        }

        debug(sprintf('Importing tweets from: %s', $f));
        $data = array_column($data, null, 'id');

        // merge each tweet
        foreach ($data as $tweet_id => $tweet) {

            // didn't exist, add to $tweets and continue
            if (!array_key_exists($tweet_id, $tweets)) {
                if ($do['tweets-all']) {
                    debug(sprintf('Adding new tweet: %d', $tweet_id));
                }
                $tweets[$tweet_id] = $tweet;
                continue;
            } else {
                // created_at is missing the time for most tweets before 2010/11
                unset($tweet['created_at']);
            }

            // already in $tweets, merge it
            $tweets[$tweet_id] = array_replace_recursive($tweets[$tweet_id],
                $tweet);
        }
    }

    unset($data);
}


//-----------------------------------------------------------------------------
// load in (if previously saved) list of resolved urls => target

$file_urls = $dir . '/urls.json';
$urls      = json_load($file_urls);
if (!is_string($urls)) {
    verbose("Loaded previously saved urls from 'urls.json'");
} else {
    $errors[] = $urls; // non-fatal so continue
    $urls     = [];
}

verbose(sprintf("URLs loaded: %d", count($urls)));

// summarise the number of source urls and target urls by host and tidy-up urls
if (DEBUG && !empty($urls)) {
    $src_hosts    = [];
    $target_hosts = [];
    $unresolved   = [];
    $curl_errors  = [];
    foreach ($urls as $url => $target) {

        $u = parse_url($url);
        if (!empty($u['host'])) {
            $src_hosts[] = $u['host'];
        }

        $t = parse_url($target);
        if (array_key_exists('host', $t) && !empty($t['host'])) {
            $target_hosts[] = $t['host'];
        } else if (count(1 == count($t))) {
            $unresolved[$url] = $target;
            if (!array_key_exists($target, $curl_errors)) {
                $curl_errors[$target] = 0;
            }
            $curl_errors[$target] ++;
        }
    }

    $src_hosts    = array_count_values($src_hosts);
    $target_hosts = array_count_values($target_hosts);
    ksort($target_hosts);
    ksort($unresolved);
    ksort($curl_errors);

    debug('All source URL hosts:', $src_hosts);

    foreach ($target_hosts as $host => $count) {
        if ($count < 25) {
            unset($target_hosts[$host]);
        }
    }
    debug('Most popular target hosts:', $target_hosts);
    debug('Previous failed cURL targets:', $unresolved);
    debug('Summary failed cURL errors:', $curl_errors);

    debug("Unresolved short URL TARGET hosts and count of same short URL SOURCE hosts:");
    foreach ($target_hosts as $host => $count) {
        if (in_array($host, $url_shorteners)) {
            debug(sprintf("TARGET: $host (%d)", $count));
            if (array_key_exists($host, $src_hosts)) {
                debug(sprintf("SOURCE: $host (%d)", $src_hosts[$host]));
            }
        }
    }

    unset($src_hosts);
    unset($target_hosts);
    unset($unresolved);
    unset($curl_errors);
}

//-----------------------------------------------------------------------------
// list all users

verbose("Getting all users mentioned in tweets…");

$users = json_load($users_filename);
if (!is_string($users)) {
    $users_count = count($users);
    verbose(sprintf("Loaded %d previously saved users from '%s'", $users_count,
            $users_filename));
} else {
    $users       = [];
    $users_count = 0;
}

//-----------------------------------------------------------------------------
// filter tweets on the keys specified on the  command-line

if ($do['keys-required']) {
    $required_keys = [];
    if (!empty($options['keys-required'])) {
        $required_keys = $options['keys-required'];
    }

    if (!empty($required_keys)) {
        $required_keys = preg_split("/,/", $required_keys);
        if (!empty($required_keys)) {
            $required_keys = array_unique($required_keys);
            sort($required_keys);
        }
    }
}

//-----------------------------------------------------------------------------
// post-process fetched tweets n $data

if (!empty($tweets) && is_array($tweets)) {

    verbose("Post-processing tweets…");

    foreach ($tweets as $tweet_id => $tweet) {

        // must contain all the keys to be included
        if ($do['keys-required'] && !empty($required_keys) && is_array($required_keys)
            && count($required_keys)) {
            $contains_required = true;
            foreach ($required_keys as $key) {
                if (!array_key_exists($key, $tweet)) {
                    $contains_required = false;
                    break;
                }
            }
            if (!$contains_required) {
                $tweets_count--;
                continue;
            }
        }

        // this situation occurs when importing grailbird js files
        if (!array_key_exists('full_text', $tweet)) {
            $tweet['full_text'] = $tweet['text'];
        }

        // drop retwwets if required
        // drop mentions (on initial tweet char being @)
        $is_rt = 'RT' == substr($tweet['full_text'], 0, 2);
        if (($do['no-retweets'] && $is_rt) ||
            ($do['no-mentions'] && '@' == substr($tweet['full_text'], 0, 1))) {
            $tweets_count--;
            continue;
        }
        // get the RT'd username and save to 'rt'
        if ($is_rt) {
            if (preg_match("/^RT\s+@(?P<screen_name>[^:\s]+)/i",
                    $tweet['full_text'], $matches)) {
                $tweet['rt'] = $matches['screen_name']; // set RT'd user
            }
        }

        // create unix timestamp 'created_at_unixtime' converted from date/time
        if (!array_key_exists('created_at_unixtime', $tweet)) {
            $tweet['created_at_unixtime'] = strtotime($tweet['created_at']);
        }

        // skip tweets based on date range
        $unixtime = $tweet['created_at_unixtime'];
        if (!empty($date_from) && $unixtime < $date_from) {
            $tweets_count--;
            continue;
        }
        if (!empty($date_to) && $unixtime > $date_to) {
            $tweets_count--;
            continue;
        }

        // filter on regular expression
        if (!empty($regexp)) {
            if (1 !== preg_match($regexp, $tweet['full_text'], $matches)) {
                $tweets_count--;
                continue;
            } else if (!empty($regexp_save)) {
                // add regular expression result to tweet
                $tweet['regexps'][] = [
                    'name'    => $regexp_save,
                    'regexp'  => $regexp,
                    'matches' => $matches
                ];
            }
        }

        // if there was no previous 'text' key, use original 'full_text' of tweet
        if (empty($tweet['text'])) {
            $tweet['text'] = $tweet['full_text'];
        }

        // create new attribute 'text', tidying-up last characters
        $full_text  = $tweet['full_text'];
        $final_char = urlencode(substr($full_text, -1));
        if (!empty($final_char) && '%A6' == $final_char) { // trim if underscore
            $p         = strrpos($full_text, ' ');
            $char      = substr($full_text, $p + 1, 1);
            $full_text = trim(substr($full_text, 0, $p));
            if ('h' !== $char) { // anything ending with h like http
                $full_text .= '…';
            }
        }
        $tweet['text'] = $full_text;

        // search & replace to apply at the end to 'text'
        $search  = $replace = [];

        // expand urls which are already embedded in the tweet json data
        // so no look-up is required of them
        if ($do['urls-expand'] && !empty($tweet['entities']['urls'])) {
            $search  = $replace = [];
            foreach ($tweet['entities']['urls'] as $entity) {
                $search[]  = $entity['url'];
                $replace[] = $entity['expanded_url'];

                // if expanded url is a short url set to null, to perform search later
                $parts = parse_url($entity['expanded_url']);
                if (false !== $parts && count($parts) > 1 && in_array(strtolower($parts['host']),
                        $url_shorteners)) {
                    if (!array_key_exists($entity['expanded_url'], $urls)) {
                        $urls[$entity['expanded_url']] = null; // null urls will be resolved later
                    }
                } else {
                    // add url to resolved list
                    if (!empty($entity['expanded_url'])) {
                        if (empty($urls[$entity['url']])) {
                            $urls[$entity['url']] = $entity['expanded_url'];
                        }
                    }
                }
            }
        }

        // perform search/replace on 'text'
        $tweet['text'] = trim(str_replace($search, $replace, $tweet['text']));

        // detect the URLs to resolve
        if ($do['urls-resolve']) {
            if (preg_match_all(
                    '/(?P<url>http[s]?:\/\/[^\s]+[^\.\s]+)/i', $tweet['text'],
                    $matches
                )) {
                foreach ($matches['url'] as $url) {
                    $parts = parse_url($url);
                    if (false == $parts || count($parts) <= 1) {
                        continue;
                    }
                    if (in_array(strtolower($parts['host']), $url_shorteners)) {
                        if (!array_key_exists($url, $urls)) {
                            $urls[$url] = null; // null urls will be resolved later
                        }
                    }
                }
            }
        }

        // perform search/replace on 'text'
        $tweet['text'] = trim(str_replace($search, $replace, $tweet['text']));

        // update users array
        // get user from retweeted_status/user_mentions, if exists, add/replace
        // NOTE: this only exists in the old/standard twitter backup files, not in the huge tweet.js file
        // if using therefore with --grailbird-import it will get executed
        if (array_key_exists('retweeted_status', $tweet)) {
            $user        = $tweet['retweeted_status']['user'];
            $screen_name = $user['screen_name'];
            if (!array_key_exists($screen_name, $users)) {
                debug(sprintf("Adding entry for user %d: @%s (%s)", $user['id'],
                        $screen_name, $user['name']));
                $users[$user['screen_name']] = $user;
            } else {
                $users[$user['screen_name']] = array_replace_recursive($users[$screen_name],
                    $user);
            }
        }

        // get users from entities/user_mentions
        // this should only add new values, not replace any, because only extra data is in retweeted_status/user entry
        if (!empty($tweet['entities']) && array_key_exists('user_mentions',
                $tweet['entities'])) {
            $user_mentions = $tweet['entities']['user_mentions'];
            foreach ($user_mentions as $i => $user) {
                if (array_key_exists($user['screen_name'], $users)) {
                    continue;
                }
                unset($user['indices']);
                debug(sprintf("Adding entry for user %d: @%s (%s)", $user['id'],
                        $user['screen_name'], $user['name']));
                // deleted users have id -1
                $users[$user['screen_name']] = $user;
            }
        }

        $tweets[$tweet_id] = $tweet;
    }
}

verbose(sprintf("Tweets available for further processing: %d", $tweets_count));

//-----------------------------------------------------------------------------
// update users profile images

if (count($users) > $users_count) {
    verbose(sprintf("New users added: %d. Total users: %d",
            count($users) - $users_count, count($users)));
    $users_count = count($users);
}
ksort($users);

$profile_images = [];
// adds 'profile_image_file ' to user for where the local file should be stored
foreach ($users as $screen_name => $user) {
    if (empty($user) || !is_array($user) || !array_key_exists('profile_image_url_https',
            $user)) {
        continue;
    }

    // create the 'profile_media' filename
    $url                                       = $user['profile_image_url_https'];
    $filename                                  = $dir . '/profile_media/' . $user['id'] . '-' . str_replace('_normal',
            '', basename($url));
    $users[$screen_name]['profile_image_file'] = $filename; // this will be the local filename
    // skip if the file exists (your own user should be here!)
    if (file_exists($filename)) {
        continue;
    }

    if ($do['list-profile-images']) {
        $profile_images[$filename] = $url;
    } else if ($do['download-profile-images']) {
        $missing_media[$filename] = $url;
    }
}

debug("Saving: $users_filename");
$save = json_save($users_filename, $users);
if (true !== $save) {
    $errors[] = "\nFailed encoding JSON output file: $users_filename\n";
    $errors[] = "\nJSON Error: $save\n";
    goto errors;
}

// go to end and write file if --list-users, or continue processing after
if ($do['list-users']) {
    $output = [];
    unset($tweets);
    goto output;
}

// finish if listing profile images
if ($do['list-profile-images']) {
    ksort($profile_images);
    $output = $profile_images;
    goto output;
}

//-----------------------------------------------------------------------------
// fetch local files for each tweet and add attribute to array which are
// video, images or other types of files

if ($do['local'] && !empty($tweets) && is_array($tweets)) {

    verbose("Searching tweets for media files...");

    // detect the locally saved twitter media files
    $to_delete     = []; // files to delete
    $missing_media = []; // missing local media files, [filename => source url]


    foreach ($tweets as $tweet_id => $tweet) {

        // for removing links to local media files in tweet text
        $search    = $replace   = [];
        $full_text = $tweet['text'];

        // find the files for the tweet
        if (!empty($tweet['entities']['media'])) {
            $extended_entities = empty($tweet['extended_entities']['media']) ? [
                ] : $tweet['extended_entities']['media'];
            foreach ([$tweet['entities']['media'], $extended_entities] as
                    $entities) {
                if (empty($entities)) {
                    continue;
                }
                foreach ($entities as $entity) {
                    // construct the local filename, then later check it exists
                    $media_file   = basename($entity['media_url_https']);
                    $media_file2  = $tweet_id . '-' . $media_file;
                    $search_files = [
                        $media_file  => $media_file,
                        $media_file2 => $media_file2
                    ];
                    if (array_key_exists('source_status_id', $entity)) {
                        $media_file3                = $entity['source_status_id'] . '-' . $media_file;
                        $search_files[$media_file3] = $media_file3;
                    }

                    // check if the filename is just {id}.{ext} instead of {tweet_id}-{id}.{ext}
                    $found = false; // found local file
                    foreach ($search_files as $file) {
                        if (array_key_exists($file, $images)) {
                            $tweet['images'][$file] = $images[$file];
                            $found                  = true;
                            break;
                        } else if (array_key_exists($file, $videos)) {
                            $tweet['videos'][$file] = $videos[$file];
                            $found                  = true;
                            break;
                        } else if (array_key_exists($file, $files)) {
                            $tweet['files'][$file] = $files[$file];
                            $found                 = true;
                            break;
                        }
                    }
                    if (empty($found)) {
                        if (!array_key_exists($media_file, $missing_media)) {
                            debug(sprintf("Missing media file: %s", $media_file),
                                $entity);
                            $media_file                 = sprintf($dir . '/tweet_media/%s',
                                $media_file);
                            $missing_media[$media_file] = $entity['media_url_https'];
                        }
                    }
                }
            }
        }

        // find the locally saved video files
        if (!empty($tweet['extended_entities']['media'])) {

            foreach ($tweet['extended_entities']['media'] as $entity) {

                // detect the video files if different bitrates for same content
                if (!empty($entity['video_info']['variants'])) {

                    $found    = false; // found video
                    $bitrates = []; // store filenames of local videos of different bitrates

                    foreach ($entity['video_info']['variants'] as $video) {

                        if (!array_key_exists('bitrate', $video)) {
                            continue;
                        }

                        // construct the local filename, then later check it exists
                        $media_file   = basename($video['url']);
                        $media_file2  = $tweet_id . '-' . $media_file;
                        $search_files = [
                            $media_file  => $media_file,
                            $media_file2 => $media_file2
                        ];
                        if (array_key_exists('source_status_id', $entity)) {
                            $media_file3                = $entity['source_status_id'] . '-' . $media_file;
                            $search_files[$media_file3] = $media_file3;
                        }

                        foreach ($search_files as $filename) {
                            // check if the video file exists by detecting filename to search for
                            if (array_key_exists($filename, $videos)) {
                                $found                       = true;
                                //debug("Found video: $filename");
                                $tweet['videos'][$filename]  = $videos[$filename];
                                $bitrates[$video['bitrate']] = $filename;
                            } else {
                                $filename = substr($filename, 0,
                                    strpos($filename, '?'));
                                if (array_key_exists($filename, $videos)) {
                                    $found                       = true;
                                    //debug("Found video 2: $filename");
                                    $tweet['videos'][$filename]  = $videos[$filename];
                                    $bitrates[$video['bitrate']] = $filename;
                                }
                            }
                        }

                        // remove the low bitrate files from new 'videos' attribute
                        if (!empty($bitrates)) {
                            $max  = max(array_keys($bitrates)); // keep max bitrate file
                            $keep = $bitrates[$max];
                            foreach ($tweet['videos'] as $filename => $path) {
                                if (file_exists($path) && 0 !== filesize($path)) {
                                    if ($keep !== $filename) {
                                        //debug("Found video to delete: $path");
                                        // remove low bit-rate files to space space
                                        if (!array_key_exists($path, $to_delete)) {
                                            $to_delete[$path] = $path; // these are to delete if CLI option specified
                                        }
                                        unset($tweet['videos'][$filename]);
                                    }
                                }
                            }
                        }
                    }
                    if (!$found && count($bitrates)) { // missing at least one local video for the various bitrates
                        debug("Found no video variant:",
                            $entity['video_info']['variants']);
                        // detect highest bitrate url
                        $bitrates = [];
                        foreach ($entity['video_info']['variants'] as $k =>
                                $variant) {
                            if (!array_key_exists('bitrate', $variant)) {
                                continue;
                            }
                            $bitrates[$variant['bitrate']] = $variant['url'];
                        }
                        $url                        = $bitrates[max(array_keys($bitrates))];
                        debug("Highest bitrate: ", $url);
                        $media_file                 = sprintf($dir . '/tweet_media/%s',
                            basename($url));
                        $missing_media[$media_file] = $url;
                    }
                }
            }
        }

        // perform the search/replace on urls in 'text'
        $tweet['text'] = trim(str_replace($search, $replace, $tweet['text']));

        $tweets[$tweet_id] = $tweet;
    }

    // only delete if the command line switch was specified
    if (count($to_delete)) {
        foreach ($to_delete as $path) {
            if (!TEST && UNLINK) {
                verbose('DELETING: ' . $path);
                unlink($path);
            } else {
                verbose('DELETING (NOT!): ' . $path);
            }
        }
        unset($to_delete);
    }

    $content['videos'] = files_videos($dir, true);
    $content['files']  = files_tweets($dir, true);
    $content['images'] = files_images($dir, true);

    foreach ($content as $type => $results) {
        foreach ($results as $id => $media_files) {
            if (!is_numeric($id) || !array_key_exists($id, $tweets)) {
                continue;
            }
            if (!is_array($media_files)) {
                $media_files = [$media_files];
            }
            // check tweet exists, if so, merge files into files keys
            if (empty($tweets[$id][$type])) {
                $tweets[$id][$type] = [];
            }
            array_merge($tweets[$id][$type], $media_files);
            $tweets[$id][$type] = $media_files;
        }
        unset($content[$type]);
    }
}

//-----------------------------------------------------------------------------
// organize media

if ($do['organize-media'] && !empty($tweets) && is_array($tweets)) {

    verbose("Organizing media files...");

    foreach ($tweets as $tweet_id => $tweet) {

        $tweet_media_folder = $target_folder      = $dir . '/tweet_media';
        $target_folder      = $tweet_media_folder . '/' . date('Y/m',
                $tweet['created_at_unixtime']);

        // create target folder
        if (!file_exists($target_folder)) {
            debug("Creating folder: $target_folder");
            if (!mkdir($target_folder, 0777, true)) {
                $errors[] = sprintf("Unable to create directory: %s",
                    $target_folder);
            }
        } else {
            if (!is_dir($target_folder)) {
                $errors[] = sprintf("Unable to make directory, file with same name exists: %s",
                    $target_folder);
            }
        }

        // get files for each type of content
        foreach (['images', 'videos', 'files'] as $type) {
            if (!array_key_exists($type, $tweet) || empty($tweet[$type])) {
                continue;
            }

            $content_files = $tweet[$type];
            foreach ($content_files as $filename => $path) {
                if (!file_exists($path)) {
                    continue;
                }
                if (preg_match('/^tweet_media\/\d{4}\/\d{2}\/[^\.]+\..+/i',
                        stristr($path, 'tweet_media'), $matches)) {
                    continue;
                }
                // check target file exists
                $target_file = $target_folder . '/' . $filename;
                if ($path == $target_file) {
                    continue;
                }
                if (file_exists($target_file)) {
                    if (file_exists($path) && filesize($path) == filesize($target_file)) {
                        // identical files, remove first file
                        if (!TEST && UNLINK) {
                            unlink($path);
                            continue;
                        }
                    }
                    $errors[] = "Target file already exists:\n\t\t$target_file";
                }
                // rename file
                debug(sprintf("Moving:\n\t%s\n\t%s", $path, $target_file));
                if (!rename($path, $target_file)) {
                    $errors[] = sprintf("Renaming failed\n\t\t%s\n\t\t%s",
                        $path, $target_file);
                    goto errors;
                }
            }
        }
    }

    // clean-up hanging files in tweets_media
    $tweet_media_files = files_list($tweet_media_folder);
    foreach ($tweet_media_files as $file => $path) {
        // skip if it's in a folder of yyyy/mm
        if (empty($file) || empty($path) || preg_match('/^tweet_media\/\d{4}\/\d{2}\/[^\.]+\..+/i',
                stristr($path, 'tweet_media'), $matches)) {
            continue;
        }

        // skip if filename not {99999-aaaaa.ext}
        if (!preg_match('/(?P<tweet_id>^[\d]+)[-](?P<id>[^\.]+\..+)/i', $file,
                $matches)) {
            //debug(sprintf("Bad filename:\n\t%s",  $path));
            continue;
        }

        // if no tweet for the id, delete file
        $tweet_id = $matches['tweet_id'];
        if (!array_key_exists($tweet_id, $tweets) && $tweet_id > 9999999) {
            debug(sprintf("Deleting:\n\t%s", $path));
            if (!TEST && UNLINK) {
                unlink($path);
            } else {
                debug('NOT!');
            }
            continue;
        }

        $target_folder = $tweet_media_folder . '/' . date('Y/m',
                $tweet['created_at_unixtime']);
        $target_file   = $target_folder . '/' . $filename;
        if (file_exists($target_file)) {
            if (file_exists($path) && filesize($path) == filesize($target_file)) {
                // identical files, remove first file
                debug("Remove: $path");
                if (!TEST && UNLINK) {
                    unlink($path);
                    continue;
                }
            }
        }
        debug(sprintf("Moving:\n\t%s\n\t%s", $path, $target_file));
    }
    if (count($errors)) {
        goto errors;
    }
    $tweets = [];
    goto output;
}

//-----------------------------------------------------------------------------
// go online and check and resolve the urls found in $urls up to this point

if ($do['urls-resolve'] && !OFFLINE) {

    verbose("Resolving URLs…");

    $urls_checked   = 0; // counter for regularly saving url check results
    $urls_remaining = count($urls);
    $urls           = array_shuffle($urls); // randomize check order

    foreach ($urls as $url => $target) {

        $urls_checked++; // increment save data counter
        $urls_remaining--;

        $parts = parse_url($url);
        if (false == $parts || count($parts) <= 1 || (array_key_exists('host',
                $parts) && in_array(strtolower($parts['host']), $hosts_expired))) {
            $urls[$url] = 0;
            continue;
        }

                // skipping youtube and twitter because in the 1000s
        if ('twitter.com' === $parts['host' ] || 'www.youtube.com' === $parts['host']) {
            continue;
        }

        // force a check!
        if (!OFFLINE && $do['urls-check-force'] && (empty($target) || is_numeric($target))) {
            $target = url_resolve($url);
            $urls[$url] = $target;
            debug(sprintf("Force checked source URL\n\t%s\n\t%s", $url, $target));
        }

        $parts2   = parse_url($target);

        // the target url exists and is a string, check if its a short url
        if (!empty($target) && is_string($target)) {
            $recheck = false;
            if (false === $parts2 || count($parts2) <= 1) {
                // bad url, so set it to 0, skip
                verbose(sprintf("Parse issue, skipping url\n\t%s", $url));
                $urls[$url] = 0;
            } else if (!in_array(strtolower($parts2['host']), $url_shorteners)) {
                // is not a shortened url, skip
                /*
                  if ($url == $target) {
                  debug(sprintf("Not checking URL\n\t%s", $url));
                  } else {
                  debug(sprintf("Not checking URL\n\t%s\n\t%s", $url, $target));
                  }
                 */
                continue;
            } else {
                if ($target === $url) {
                    continue;
                }

                // check for only changed scheme difference
                if (array_key_exists('scheme', $parts) && array_key_exists('scheme', $parts2)) {
                    unset($parts['scheme']);
                    unset($parts2['scheme']);
                    if ($parts === $parts2) {
                        continue;
                    }
                }

                // the target url was a shortened url, so find the destination of it
                verbose(sprintf("Checking short URL\n\t%s\n\t%s", $url, $target));

                // update the target url to the final destination url
                $check_start = time(); // timer
                $newtarget   = url_resolve($target);
                $urls[$url]  = $newtarget;
                if ($newtarget !== $target) {
                    verbose(sprintf("Resolved short URL\n\t%s\n\t%s\n\t%s",
                            $url, $target, $newtarget));
                }
                $target = $newtarget;

                // perform recheck if the new target is still a short url
                if (!empty($target) && is_string($target)) {
                    $parts = parse_url($target);
                    if (false === $parts || count($parts) <= 1) {
                        // bad url, so set it to 0
                        debug(sprintf("Parse issue, skipping url\n\t%s", $url));
                        $urls[$url] = 0;
                    } else if (in_array(strtolower($parts['host']),
                            $url_shorteners)) {
                        // shortened url, resolve again
                        $newtarget  = url_resolve($target);
                        $urls[$url] = $newtarget;
                    }
                }

                // sleep a bit before continuing
                $check_end = time() - $check_start;
                if ($check_end < $online_sleep_under) {
                    debug('Sleep');
                    sleep($online_sleep);
                }
            }
            continue;
        }

        // at this point the target was empty OR numeric

        if (in_array($target, $curl_errors_dead)) {
            // will not recheck urls which returned an error code from curl/wget
            //debug(sprintf("Not checking URL\n\t(%d)%s", $url, $target));
        } else if (!OFFLINE && !in_array($target, $curl_errors_dead)) {
            // find the target of the source $url
            verbose(sprintf("Checking URL %s", $url));
            $check_start = time(); // timer
            $u           = url_resolve($url); // resolve $url to find value for $target
            if (!empty($url) && is_string($u)) {
                // we found a url, so set the target in $urls
                verbose(sprintf("Found URL\n\t%s\n\t%s", $url, $u));
            } else {
                // an error occurred resolving the url
                verbose(sprintf("Failed URL\n\t%s\n\t%s", $url, $u));
            }
            $urls[$url] = $u;

            // sleep a bit before continuing
            $check_end = time() - $check_start;
            if ($check_end < $online_sleep_under) {
                debug('Sleep');
                sleep($online_sleep);
            }
        }

        // save urls every 100 which have been checked online
        if ($urls_checked % $save_every == 0) {
            debug(sprintf("[%d/%d] URLs checked/remaining.", $urls_checked,
                    $urls_remaining));
            debug("Saving: $file_urls");
            $save = json_save($file_urls, $urls);
            if (true !== $save) {
                $errors[] = "\nFailed encoding JSON output file: $file_urls\n";
                $errors[] = "\nJSON Error: $save\n";
                goto errors;
            }
        }
    }

    // save urls checked
    verbose("Total URLs checked: " . $urls_checked);
    if ($urls_checked > 0) {
        debug("Saving: $file_urls");
        $save = json_save($file_urls, $urls);
        if (true !== $save) {
            $errors[] = "\nFailed encoding JSON output file: $file_urls\n";
            $errors[] = "\nJSON Error: $save\n";
            goto errors;
        }
    }

    if (!empty($errors)) {
        goto errors;
    }
}

// this doesn't go ONLINE
if ($do['urls-resolve']) {

    // detect the https domains to update target urls using $https_domains
    verbose("Checking URLs for HTTPS hosts…");
    $https_domains = [];
    foreach ($urls as $url => $target) {
        // save final destination target url to $urls
        $parts = parse_url($target);
        if (false === $parts || count($parts) <= 1 || is_numeric($target)) {
            continue;
        }
        $host = $parts['host'];
        // is the host on https?
        if ('https' == $parts['scheme']) {
            if (!in_array($parts['host'], $https_domains)) {
                $https_domains[$host] = $host;
            }
        }
    }
    verbose(sprintf("Found %s HTTPS hosts…", count($https_domains)));

    // Update HTTPs domains
    verbose("Upgrading HTTP to HTTPS URLs…");
    foreach ($urls as $url => $target) {

        $parts = parse_url($target);
        if (!array_key_exists('scheme', $parts)) {
            continue;
        }

        // if the host is HTTP, but exists on HTTPS, convert it
        if ('http' == $parts['scheme'] && array_key_exists($parts['host'],
                $https_domains)) {
            $parts['scheme'] = 'https';
            $urls[$url]      = str_ireplace('http://', 'https://', $target);
        }

        // modify query string and URL - remove 'feature' param, fix youtube url, remove utm_* tracking
        $t = $parts;
        if (!empty($t['query'])) {
            $querystring = [];
            parse_str($t['query'], $querystring);
            if (!empty($querystring) && is_array($querystring)) {
                foreach ($querystring as $k => $v) {
                    // youtube url fix
                    if (array_key_exists('feature', $querystring) && false !== stristr($target,
                            'youtube')) {
                        $target = str_replace(['m.youtube', '&feature=youtu.be'],
                            ['www.youtube', ''], $target);
                    }
                    if ('feature' === $k || false !== stristr($k, 'utm_')) {
                        unset($querystring[$k]);
                    }
                }

                ksort($querystring);
                $querystring = http_build_query($querystring);

                // build url
                $newtarget = $t['scheme'] . '://' . $t['host'];

                // add port if non-standard port (not 80,443)
                if (array_key_exists('port', $t) && !in_array($t['port'],
                        [80, 443])) {
                    $newtarget .= ':' . $t['port'];
                }

                // add path if not / and no query string
                if (array_key_exists('path', $t) && !empty($t['path'] && !empty($querysring))) {
                    $newtarget .= $t['path'];
                }

                // add querystring
                if (!empty($querystring)) {
                    $newtarget .= '?' . $querystring;
                }

                // add fragment
                if (!empty($t['fragment'])) {
                    $newtarget .= '#' . $t['fragment'];
                }

                // update urls with new target
                if ($target !== $newtarget) {
                    $target     = $newtarget;
                    $urls[$url] = $target;
                }
            }
        }
    }

    // save modified $urls
    unset($https_domains);
    debug("Saving: $file_urls");
    $save = json_save($file_urls, $urls);
    if (true !== $save) {
        $errors[] = "\nFailed encoding JSON output file: $file_urls\n";
        $errors[] = "\nJSON Error: $save\n";
        goto errors;
    }
}

if (!empty($tweets) && is_array($tweets)) {

    verbose("Re-building media entities…");

    foreach ($tweets as $tweet_id => $tweet) {

        // junk to trim
        $search  = $replace = [];

        // find all urls in 'text'
        if (preg_match_all(
                '/(?P<url>http[s]?:\/\/[^\s]+[^\.\s]+)/i', $tweet['text'],
                $matches
            )
        ) {
            // check each url
            foreach ($matches['url'] as $url) {

                // slip malformed urls
                $parts = parse_url($url);
                if (false === $parts || count($parts) <= 1 || empty($parts['host'])) {
                    continue;
                }

                // check if the host is a url shortener
                // if we already have the shortened url target, then replace it with that using
                $host = $parts['host'];
                if (in_array(strtolower($host), $url_shorteners)) {
                    if (!empty($urls[$url]) && is_string($urls[$url])) {
                        $r = $urls[$url];
                        if (!is_numeric($r)) {
                            $search[]  = $url;
                            $replace[] = $r;
                        }
                    }
                } else if (!empty($urls[$url]) && !is_numeric($urls[$url])) {
                    $search[]  = $url;
                    $replace[] = $urls[$url];
                }
            }

            // perform the search/replace for usl in 'text'
            $tweet['text'] = trim(str_replace($search, $replace, $tweet['text']));
        }

        // re-build hashtag entity indexes
        $tweet_hashtags = [];
        if (preg_match_all(
                '/(?P<hashtag>#[^\s]+)[\s]?/i', $tweet['text'], $matches
            )
        ) {
            foreach ($matches['hashtag'] as $hashtag) {
                $i                = stripos($tweet['text'], $hashtag);
                $tweet_hashtags[] = [
                    "text"    => substr($hashtag, 1),
                    "indices" => [$i, $i + strlen($hashtag)]
                ];
            }
        }
        $tweet['entities']['hashtags'] = $tweet_hashtags;

        // re-build entities user mentions
        if (array_key_exists('entities', $tweet) && array_key_exists('user_mentions',
                $tweet['entities'])) {
            foreach ($tweet['entities']['user_mentions'] as $e => $entity) {
                $screen_name = $entity['screen_name'];
                $i           = strpos($tweet['text'], '@' . $screen_name);
                if (false === $i) {
                    unset($tweet['entities']['user_mentions'][$e]);
                    continue;
                }
                $entity['indices']                      = [$i, strlen($screen_name)
                    + 4];
                $tweet['entities']['user_mentions'][$e] = $entity;
            }
        }

        // re-build media entities
        if ($do['local']) {
            $found_entities = [];

            $tweet['entities']['media']          = empty($tweet['entities']['media'])
                    ? [] : $tweet['entities']['media'];
            $tweet['extended_entities']['media'] = empty($tweet['extended_entities']['media'])
                    ? [] : $tweet['extended_entities']['media'];

            foreach ([$tweet['entities']['media'], $tweet['extended_entities']['media']] as
                    $index => $entities) {

                if (empty($entities)) {
                    continue;
                }

                foreach ($entities as $e => $entity) {

                    // construct the local filename, then later check it exists
                    $media_file   = basename($entity['media_url_https']);
                    $media_file2  = $tweet_id . '-' . $media_file;
                    $search_files = [
                        $media_file  => $media_file,
                        $media_file2 => $media_file2
                    ];
                    if (array_key_exists('source_status_id', $entity)) {
                        $media_file3                = $entity['source_status_id'] . '-' . $media_file;
                        $search_files[$media_file3] = $media_file3;
                    }

                    // check if the filename is just {id}.{ext} instead of {tweet_id}-{id}.{ext}
                    $found = false; // found local file
                    foreach ($search_files as $file) {
                        if (array_key_exists($file, $images)) {
                            $path  = $images[$file];
                            $found = true;
                            break;
                        } else if (array_key_exists($file, $videos)) {
                            $path  = $videos[$file];
                            $found = true;
                            break;
                        } else if (array_key_exists($file, $files)) {
                            $path  = $files[$file];
                            $found = true;
                            break;
                        }
                    }

                    if (empty($found)) {
                        continue;
                    }

                    $i      = strlen($tweet['text']); // will append to tweet after!
                    $url    = 'file://' . $path;
                    $entity = array_replace_recursive($entity,
                        [
                        'url'             => '',
                        'expanded_url'    => '',
                        'media_url'       => $url,
                        'media_url_https' => $url,
                        'display_url'     => '',
                        'indices'         => [$i, $i + 1],
                    ]);

                    $entities[$e] = $entity;
                }

                if (0 === $index) {
                    $tweet['entities']['media'] = $entities;
                } else { // 1
                    $tweet['extended_entities'] = $entities;
                }
            }
        }

        // re-build url entity indexes
        $tweet_urls = [];
        // find all urls in 'text'
        if (preg_match_all(
                '/(?P<url>http[s]?:\/\/[^\s]+[^\.\s]+)/i', $tweet['text'],
                $matches
            )
        ) {
            foreach ($matches['url'] as $url) {
                $parts = parse_url($url);
                $i     = stripos($tweet['text'], $url);
                if (false === $i) {
                    debug("Failed searching text for URL:", $tweet);
                }
                $host = $parts['host'];
                if (0 === strpos($host, 'www.')) {
                    $host = substr($host, 4);
                } else if (0 === strpos($host, 'm.')) {
                    $host = substr($host, 2);
                } else if (0 === strpos($host, 'en.')) {
                    $host = substr($host, 3);
                }
                if (!array_key_exists('path', $parts)) {
                    $display_url = '(' . sprintf("%s", $host) . ')';
                } else {
                    $display_url = '(' . sprintf("%s%s", $host, $parts['path']) . ')';
                }
                $tweet_urls[] = [
                    "url"          => $url,
                    "expanded_url" => $url,
                    "display_url"  => $display_url,
                    "indices"      => [$i, $i + strlen($url)]
                ];
            }
        }
        $tweet['entities']['urls'] = $tweet_urls;


        // find twitpic, remove if in files
        $search  = $replace = [];
        $text    = $tweet['text'];

        // get the other image urls
        if ($do['local'] && array_key_exists('images', $tweet)) {
            if (preg_match_all('/(?P<url>http[s]?:\/\/[^\s]+[^\.\s]+)/i', $text,
                    $matches)) {
                foreach ($matches['url'] as $url) {
                    $parts = parse_url($url);
                    if (empty($parts) || !is_array($parts) || !array_key_exists('host',
                            $parts)) {
                        continue;
                    }
                    $found    = false;
                    $image_id = null;
                    switch ($parts['host']) {
                        case 'twitpic.com':
                            $image_id = $tweet['id'] . '-' . stristr($url,
                                    substr($parts['path'], 1));
                            foreach (['jpg', 'png', 'jpeg', 'gif'] as $ext) {
                                $image_file = $image_id . '.' . $ext;
                                if (array_key_exists($image_file,
                                        $tweet['images'])) {
                                    $search[]  = $url;
                                    $replace[] = '';
                                    $found     = true;
                                    break;
                                }
                            }
                            break;
                        default:
                            continue;
                            break;
                    }

                    if ($do['list-missing-media'] || $do['download-missing-media']) {
                        if (false === $found && null !== $image_id) {
                            debug("Not found: \n$url\n$image_id");
                            $path                 = $dir . '/' . 'tweet_media/' . $image_id . '.jpg';
                            $missing_media[$path] = $url;
                        }
                    }
                }
            }
        }

        // perform the search/replace on urls in 'text'
        $text                        = trim(str_replace($search, $replace, $text));
        $tweet['display_text_range'] = [0, strlen($text)];
        $tweet['text']               = $text;
        ksort($tweet);
        $tweets[$tweet_id]           = $tweet;
    }

    ksort($tweets);

    // save updated $urls
    debug("Saving: $file_urls");
    ksort($urls);
    $save = json_save($file_urls, $urls);
    if (true !== $save) {
        $errors[] = "\nFailed encoding JSON output file: $file_urls\n";
        $errors[] = "\nJSON Error: $save\n";
        goto errors;
    }
}

//-----------------------------------------------------------------------------
// check all urls


if ($do['urls-check']) {

    debug("Performing full destination URLs check (except youtube and twitter!). NOTE: This will only update the 'urls.json' file.");

    // check urls in a random order
    $urls           = array_shuffle($urls);
    $urls_checked   = 0; // counter for regularly saving url check results
    $urls_remaining = count($urls);
    $urls_changed   = 0;
    $urls_bad       = 0;

    foreach ($urls as $url => $target) {

        $urls_checked++; // increment save data counter
        $urls_remaining--; // decrement urls remaining

        if (is_numeric($target)) {
            continue;
        }

        // skipping youtube and twitter because in the 1000s
        $parts = parse_url($target);
        if (empty($parts) || !array_key_exists('host', $parts) || 'twitter.com' == $parts['host']
            || 'www.youtube.com' == $parts['host'] || in_array(strtolower($parts['host']),
                $url_shorteners)) {
            continue;
        }

        verbose(sprintf("[%06d/%06d %06d %06d] Checking URL:\n\t%s\n\t",
                $urls_checked, $urls_remaining, $urls_changed, $urls_bad,
                $target));

        $result = url_resolve($target);

        if ($result !== $target) {
            verbose(sprintf("\nURL updated:\n\t%s\n", $result));
            // only overwrite target if it is good, do not replace with error code!
            if (empty($target) || is_numeric($target)) {
                $urls_bad++;
                continue;
            } else {
                $urls_changed++;
                $urls[$url] = $result;
            }
        }

        if ($urls_checked % $save_every == 0) {
            debug("Saving: $file_urls");
            $save = json_save($file_urls, $urls);
            if (true !== $save) {
                $errors[] = "\nFailed encoding JSON output file: $file_urls\n";
                $errors[] = "\nJSON Error: $save\n";
                goto errors;
            }
        }
    }

    // save updated $urls
    debug("Saving: $file_urls");
    ksort($urls);
    $save = json_save($file_urls, $urls);
    if (true !== $save) {
        $errors[] = "\nFailed encoding JSON output file: $file_urls\n";
        $errors[] = "\nJSON Error: $save\n";
        goto errors;
    }

    verbose(sprintf("Finished URL target checking.\n\tURLs checked: %06d\n\tURLs changed:%06d\n\tURLs bad: %06d\n\t\n",
            $urls_checked, $urls_changed, $urls_bad));
}

//-----------------------------------------------------------------------------
// download missing media files
// show missing media files if --missing-media specified, and finish
if ($do['list-missing-media']) {
    $output = $missing_media;
    goto output;
}

if ($do['download-missing-media'] || $do['download-profile-images']) {
    if (!empty($missing_media)) {
        verbose(sprintf("Downloading:\n\t%s\n\t%s", $url, $path));
        // download each missing file
        $i = 0;
        foreach ($missing_media as $file => $url) {
            $result = url_download($url, $file);
            if (true !== $result) {
                $errors[] = sprintf("Error downloading %s to %s: %s", $url,
                    $file, $result);
                continue;
            }
            $i++;
            sleep(0.2);
        }
        verbose("Finished fetching missing files.");
        $output[] = sprintf("Downloaded %d/%d missing media files.", $i,
            count($missing_media));
    }
    goto output;
}

//-----------------------------------------------------------------------------
// generate 'grailbird' json files compatible with regular twitter export feature

if ($do['grailbird'] && !empty($tweets) && is_array($tweets)) {

    verbose("Creating grailbird js files…");

    // load account details
    $account_file = 'account.js';
    verbose(sprintf("Loading account details from '%s'", $account_file));
    $account      = json_load_twitter($dir, $account_file);
    if (empty($account) || is_string($account)) {
        $errors[] = 'No account file found!';
        if (is_string($account)) {
            $errors[] = 'JSON Error: ' . $account;
        }
        goto errors;
    } else {
        $account = $account[0]['account'];
    }
    verbose("Account details loaded:", $account);

    // load profile details
    $profile_file = 'profile.js';
    verbose(sprintf("Loading profile details from '%s'", $profile_file));
    $profile      = json_load_twitter($dir, $profile_file);
    if (empty($profile) || is_string($profile)) {
        $errors[] = 'No profile file found!';
        if (is_string($profile)) {
            $errors[] = 'JSON Error: ' . $profile;
        }
        goto errors;
    } else {
        $profile = $profile[0]['profile'];
    }
    verbose("Profile details loaded:", $profile);

    // create user_details.js file
    $filename     = $grailbird_dir . '/user_details.js';
    $prepend_text = 'var user_details = ';
    $user_details = [
        "screen_name" => $account['username'],
        "location"    => $profile['description']['location'],
        "full_name"   => $account['accountDisplayName'],
        "bio"         => $profile['description']['bio'],
        "id"          => $account['accountId'],
        "created_at"  => date('Y-m-d H:i:s +0000',
            strtotime($account['createdAt']))
    ];
    $save         = json_save($filename, $user_details, $prepend_text);
    if (true !== $save) {
        $errors[] = "\nFailed encoding JSON output file: $filename\n";
        $errors[] = "\nJSON Error: $save\n";
        goto errors;
    } else {
        debug(sprintf("Wrote grailbird user details data file: %s", $filename));
    }

    // every tweet must have the user info, this will be default if not set before
    $user = [
        "name"                    => $account['accountDisplayName'],
        "screen_name"             => $account['username'],
        "protected"               => false,
        "id_str"                  => $account['accountId'],
        "profile_image_url_https" => $profile['avatarMediaUrl'],
        "id"                      => (int) $account['accountId'],
        "verified"                => false
    ];

    // create payload_details.js file
    $tweets_count    = count($tweets);
    $filename        = $grailbird_dir . '/payload_details.js';
    $prepend_text    = 'var payload_details = ';
    $payload_details = [
        "tweets"     => $tweets_count,
        "created_at" => date('Y-m-d H:i:s +0000'),
        "lang"       => 'en'
    ];
    $save            = json_save($filename, $payload_details, $prepend_text);
    if (true !== $save) {
        $errors[] = "\nFailed encoding JSON output file: $filename\n";
        $errors[] = "\nJSON Error: $save\n";
        goto errors;
    } else {
        debug(sprintf("Wrote grailbird payload data file: %s", $filename));
    }

    // generate the monthly tweets files as an array from $tweets
    $month_files = [];
    foreach ($tweets as $tweet_id => $tweet_default) {

        $tweet = $tweet_default; // need to modify this to match grailbird files

        $tweet['created_at'] = date('Y-m-d h:i:s +0000',
            $tweet['created_at_unixtime']);
        $month_file          = date('Y_m', $tweet['created_at_unixtime']);

        if (!array_key_exists('user', $tweet)) {
            // not an RT so use loaded $user information
            if (!array_key_exists('rt', $tweet)) {
                $tweet['user'] = $user;
            } else {
                // is an rt, add user for it
                $screen_name = $tweet['rt'];
                if (array_key_exists($screen_name, $users)) {
                    $u = $users[$screen_name];
                    // create missing data if not present
                    if (!array_key_exists('profile_image_url_https', $u)) {
                        $u                   = array_replace_recursive([
                            'name'                    => $screen_name,
                            'screen_name'             => $screen_name,
                            'protected'               => false,
                            'id_str'                  => -1,
                            'id'                      => -1,
                            'verified'                => false,
                            'profile_image_url_https' => ''
                            ], $u);
                        $users[$screen_name] = $u;
                    }
                } else {
                    // create dummy expired data for user
                    $u                   = [
                        'name'                    => $screen_name,
                        'screen_name'             => $screen_name,
                        'protected'               => false,
                        'id_str'                  => -1,
                        'id'                      => -1,
                        'verified'                => false,
                        'profile_image_url_https' => ''
                    ];
                    $users[$screen_name] = $u;
                }
                $tweet['user'] = $u;
            }
        }

        // remove keys not in grailbord
        foreach (['truncated', 'retweet_count', 'retweeted', 'favorited', 'favorite_count',
        'possibly_sensitive', 'rt',
        'lang', 'display_text_range', 'full_text', 'created_at_unixtime', 'extended_entities'] as
                $key) {
            if (array_key_exists($key, $tweet)) {
                unset($tweet[$key]);
            }
        }

        foreach (['id', 'in_reply_to_user_id', 'in_reply_to_status_id'] as $k) {
            if (array_key_exists($k, $tweet)) {
                $tweet[$k] = (int) $tweet[$k];
            }
        }

        $month_files[$month_file][] = $tweet;
    }

    // write the monthly tweets array to individual files
    verbose(sprintf("Writing %d year_month.js data files for grailbird…",
            count($month_files)));
    krsort($month_files);
    $tweet_index = []; // for tweet_index.js file
    foreach ($month_files as $yyyymm => $month_tweets) {
        unset($month_files[$yyyymm]);
        $year          = (int) substr($yyyymm, 0, 4);
        $month         = (int) substr($yyyymm, 5);
        $tweet_index[] = [
            "file_name"   => sprintf("data/js/tweets/%04d_%02d.js", $year,
                $month),
            "year"        => $year,
            "var_name"    => sprintf("tweets_%04d_%02d", $year, $month),
            "tweet_count" => count($month_tweets),
            "month"       => $month
        ];
        ksort($month_tweets); // to start with current year at top of grailbird app page
        $filename      = $grailbird_dir . '/' . $yyyymm . '.js';
        $prepend_text  = 'Grailbird.data.tweets_' . $yyyymm . ' = ' . "\n";
        $save          = json_save($filename, $month_tweets, $prepend_text);
        if (true !== $save) {
            $errors[] = "\nFailed encoding JSON output file: $filename\n";
            $errors[] = "\nJSON Error: $save\n";
            goto errors;
        } else {
            debug(sprintf("Wrote grailbird monthly tweets data file: %s",
                    $filename));
        }
    }

    // create tweet_index.js file
    $filename     = $grailbird_dir . '/tweet_index.js';
    $prepend_text = 'var tweet_index = ';
    $save         = json_save($filename, $tweet_index, $prepend_text);
    if (true !== $save) {
        $errors[] = "\nFailed encoding JSON output file: $filename\n";
        $errors[] = "\nJSON Error: $save\n";
        goto errors;
    } else {
        debug(sprintf("Wrote grailbird tweet index data file: %s", $filename));
    }
}


//-----------------------------------------------------------------------------
// we have a $tweets array of all of the tweets we can do our next
// stripping out of the attributes/keys if they were specified
// on the command-line

$remove_keys = [];

if (!empty($options['k'])) {
    $remove_keys = $options['k'];
} elseif (!empty($options['keys-remove'])) {
    $remove_keys = $options['keys-remove'];
}

if (!empty($remove_keys)) {
    $remove_keys = preg_split("/,/", $remove_keys);
    verbose('Removing keys from tweets…', $remove_keys);
    if (!empty($remove_keys) && is_array($remove_keys)) {
        $remove_keys = array_unique($remove_keys);
        sort($only_keys);
        $tweets      = array_clear($tweets, $remove_keys);
    }
}

//-----------------------------------------------------------------------------
// filter tweets on the keys specified on the  command-line

if ($do['keys-filter']) {

    $only_keys = [];
    if (!empty($options['s'])) {
        $only_keys = $options['s'];
    } elseif (!empty($options['keys-filter'])) {
        $only_keys = $options['keys-filter'];
    }

    if (!empty($only_keys)) {
        $only_keys = preg_split("/,/", $only_keys);

        if (!empty($only_keys) && !empty($tweets) && is_array($tweets)) {
            verbose('Filtering tweets to show only keys…', $only_keys);
            $only_keys = array_unique($only_keys);
            sort($only_keys);
            foreach ($tweets as $tweet_id => $tweet) {
                foreach ($tweet as $k => $v) {
                    if (!in_array($k, $only_keys)) {
                        unset($tweet[$k]);
                    }
                }
                $tweets[$tweet_id] = $tweet;
            }
        }
    }
}

//-----------------------------------------------------------------------------
// final output of data

output:

// display any errors
if (!empty($errors)) {
    goto errors;
}

unset($urls);

// write tweets array to file by default if no other output specified
if (empty($output) && !empty($tweets) && is_array($tweets)) {
    debug('Removing empty values from tweets again…');
    $tweets = array_clear($tweets);
    $output = $tweets;
    unset($tweets);
}

// only write/display output if we have some!
if (!empty($output)) {

    if (empty($output_filename)) {
        $output_filename = 'output.' . OUTPUT_FORMAT;
    }
    $file = $output_dir . '/' . $output_filename;

    switch (OUTPUT_FORMAT) {
        case 'txt':
            if (!empty($output) && is_array($output)) {
                foreach ($output as $o) {
                    if (is_array($o) || is_object($o)) {
                        print_r($o);
                    } else {
                        echo "$o\n";
                    }
                }
            }
            break;

        case 'php':
            $save = serialize_save($file, $output);
            if (true !== $save) {
                $errors[] = "\nFailed encoding JSON output file: $file\n";
                goto errors;
            } else {
                verbose(sprintf("PHP serialized data written to output file:\n\t%s (%d bytes)\n",
                        $file, filesize($file)));
            }
            break;

        default:
        case 'json':
            $save = json_save($file, $output);
            if (true !== $save) {
                $errors[] = "\nFailed encoding JSON output file: $output_filename\n";
                $errors[] = "\nJSON Error: $save\n";
                goto errors;
            } else {
                verbose(sprintf("JSON written to output file:\n\t%s (%d bytes)\n",
                        $file, filesize($file)));
            }
            break;
    }
}

debug(sprintf("Memory used (%s) MB (current/peak).", get_memory_used()));
output("\n");
exit;

//-----------------------------------------------------------------------------
// functions used above

/**
 * Output string, to STDERR if available
 *
 * @param  string { string to output
 * @param  boolean $STDERR write to stderr if it is available
 */
function output($text, $STDERR = true)
{
    if (!empty($STDERR) && defined('STDERR')) {
        fwrite(STDERR, $text);
    } else {
        echo $text;
    }
}


/**
 * Dump debug data if DEBUG constant is set
 *
 * @param  optional string $string string to output
 * @param  optional mixed $data to dump
 * @return boolean true if string output, false if not
 */
function debug($string = '', $data = [])
{
    if (DEBUG) {
        output(trim('[D ' . get_memory_used() . '] ' . $string) . "\n");
        if (!empty($data)) {
            output(print_r($data, 1));
        }
        return true;
    }
    return false;
}


/**
 * Output string if VERBOSE constant is set
 *
 * @param  string $string string to output
 * @param  optional mixed $data to dump
 * @return boolean true if string output, false if not
 */
function verbose($string, $data = [])
{
    if (VERBOSE && !empty($string)) {
        output(trim('[V' . ((DEBUG) ? ' ' . get_memory_used() : '') . '] ' . $string) . "\n");
        if (!empty($data)) {
            output(print_r($data, 1));
        }
        return true;
    }
    return false;
}


/**
 * Return the memory used by the script, (current/peak)
 *
 * @return string memory used
 */
function get_memory_used()
{
    return(
        ceil(memory_get_usage() / 1024 / 1024) . '/' .
        ceil(memory_get_peak_usage() / 1024 / 1024));
}


/**
 * check required commands installed and get path
 *
 * @param  array $requirements [][command -> description]
 * @return mixed array [command -> path] or string errors
 */
function get_commands($requirements = [])
{
    static $commands = []; // cli command paths

    $found = true;
    foreach ($requirements as $tool => $description) {
        if (!array_key_exists($tool, $commands)) {
            $found = false;
            break;
        }
    }
    if ($found) {
        return $commands;
    }

    $errors = [];
    foreach ($requirements as $tool => $description) {
        $cmd = cmd_execute("which $tool");
        if (empty($cmd)) {
            $errors[] = "Error: Missing requirement: $tool - " . $description;
        } else {
            $commands[$tool] = $cmd[0];
        }
    }

    if (!empty($errors)) {
        output(join("\n", $errors) . "\n");
    }

    return $commands;
}


/**
 * Execute a command and return streams as an array of
 * stdin, stdout, stderr
 *
 * @param  string $cmd command to execute
 * @return array|false array $streams | boolean false if failure
 * @see    https://secure.php.net/manual/en/function.proc-open.php
 */
function shell_execute($cmd)
{
    $process = proc_open(
        $cmd,
        [
        ['pipe', 'r'],
        ['pipe', 'w'],
        ['pipe', 'w']
        ], $pipes
    );
    if (is_resource($process)) {
        $streams = [];
        foreach ($pipes as $p => $v) {
            $streams[] = stream_get_contents($pipes[$p]);
        }
        proc_close($process);
        return [
            'stdin'  => $streams[0],
            'stdout' => $streams[1],
            'stderr' => $streams[2]
        ];
    }
    return false;
}


/**
 * Execute a command and return output of stdout or throw exception of stderr
 *
 * @param  string $cmd command to execute
 * @param  boolean $split split returned results? default on newline
 * @param  string $exp regular expression to preg_split to split on
 * @return mixed string $stdout | Exception if failure
 * @see    shell_execute($cmd)
 */
function cmd_execute($cmd, $split = true, $exp = "/\n/")
{
    $result = shell_execute($cmd);
    if (!empty($result['stderr'])) {
        throw new Exception($result['stderr']);
    }
    $data = $result['stdout'];
    if (empty($split) || empty($exp) || empty($data)) {
        return $data;
    }
    return preg_split($exp, $data);
}


/**
 * Fetch all tweet files in subfolder 'tweet_files'
 *
 * @param  string $dir to search
 * @return array [][basename => target] OR if group set [][id][basename => target]
 */
function files_tweets($dir, $group = false)
{
    $tfiles = [];
    $files  = files_list($dir);
    foreach ($files as $f => $file) {
        if (stristr($file, '/tweet_files/') !== false) {
            if (preg_match('/^(?<id>[0-9]+)[-][^\.]+\..+/', $f, $matches)) {
                if ($group) {
                    $tfiles[$matches['id']][$f] = $file;
                } else {
                    $tfiles[$f] = $file;
                }
            }
        }
    }

    return $tfiles;
}


/**
 * Fetch all files in folder
 *
 * @param  string $dir to search
 * @param  boolean $use_cache use cached files
 * @return array $sort sort files or not
 */
function files_list($dir, $sort = true, $use_cache = true)
{
    static $cache = [];

    // retrieve from cache (if cached) and using cache
    $key = md5($dir . (int) $sort);
    if (!empty($use_cache)) {
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
    }

    $commands = get_commands();
    $cmd      = $commands['find'] . ' ' . "$dir ";
    $cmd      .= '-type f -print';
    $files    = cmd_execute($cmd);
    if (empty($files)) {
        return [];
    }

    unset($files[0]); // first line is blank
    // strip hidden meta files
    foreach ($files as $i => $file) {
        unset($files[$i]);
        if (false !== stristr($file, '/._') || 0 === filesize($file)) {
            continue;
        }
        $files[basename($file)] = $file;
    }

    if (!empty($sort)) {
        natcasesort($files);
    }

    $cache[$key] = $files;
    return $files;
}


/**
 * Fetch all tweet images in dir (named 99999-XXXXXX .png, .jpg, .jpeg.gif)
 *
 * @param  string $dir to search
 * @return array [][basename => target] OR if group set [][id][basename => target]
 */
function files_images($dir, $group = false)
{
    $images = [];
    $files  = files_list($dir);
    foreach ($files as $f => $file) {
        if (stristr($f, '.jp') !== false || stristr($f, '.png') !== false || stristr($f,
                '.gif') !== false) {
            if (preg_match('/^(?<id>[0-9]+)[-][^\.]+\..+/', $f, $matches)) {
                if ($group) {
                    $images[$matches['id']][$f] = $file;
                    continue;
                }
            }
            if (0 !== filesize($file)) {
                $images[$f] = $file;
            }
        }
    }

    return $images;
}


/**
 * Fetch all tweet videos in dir (named 99999-XXXXXX .mp4)
 *
 * @param  string $dir to search
 * @return array [][basename => target] OR if group set [][id][basename => target]
 */
function files_videos($dir, $group = false)
{
    $videos = [];
    $files  = files_list($dir);
    foreach ($files as $f => $file) {
        if (stristr($f, '.mp4') !== false) {
            if (preg_match('/^(?<id>[0-9]+)[-][^\.]+\..+/', $f, $matches)) {
                if ($group) {
                    $videos[$matches['id']][$f] = $file;
                    continue;
                }
            }
            if (0 !== filesize($file)) {
                $videos[$f] = $file;
            }
        }
    }

    return $videos;
}


/**
 * Fetch all js in dir (named .js or .json)
 *
 * @param  string $dir to search
 * @return array [][basename => target]
 */
function files_js($dir)
{
    $js_files = [];
    $files    = files_list($dir);
    foreach ($files as $f => $file) {
        if (stristr($f, '.js') !== false || stristr($f, '.json') !== false) {
            if (0 !== filesize($file)) {
                $js_files[$f] = $file;
            }
        }
    }

    return $js_files;
}


/**
 * Count the number of tweets
 *
 * @param  string $dir to search default filename 'tweet.js' for
 * @param  string $filename the filename containing the tweets
 * @return integer $data
 */
function tweets_count($dir, $filename = 'tweet.js')
{
    return count(json_load_twitter($dir, $filename));
}


/**
 * Shuffle an associative array
 *
 * @param  array $array array to shuffle
 * @return array $array shuffled
 * @see https://secure.php.net/manual/en/function.shuffle.php
 */
function array_shuffle($array)
{
    if (empty($array) || !is_array($array)) {
        return $array;
    }

    $keys = array_keys($array);
    shuffle($keys);

    $results = array();
    foreach ($keys as $key) {
        $results[$key] = $array[$key];
    }

    return $results;
}


/**
 * Clear an array of empty values
 *
 * @param  array $keys array keys to explicitly remove regardless
 * @return array the trimmed down array
 */
function array_clear($array, $keys = [])
{
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            do {
                $oldvalue = $value;
                $value    = array_clear($value, $keys);
            }
            while ($oldvalue !== $value);
            $array[$key] = array_clear($value, $keys);
        }

        if (empty($value) && 0 !== $value) {
            unset($array[$key]);
        }

        if (in_array($key, $keys, true)) {
            unset($array[$key]);
        }
    }
    return $array;
}


/**
 * Encode array character encoding recursively
 *
 * @param mixed $data
 * @param string $to_charset convert to encoding
 * @param string $from_charset convert from encoding
 * @return mixed
 */
function to_charset($data, $to_charset = 'UTF-8', $from_charset = 'auto')
{
    if (is_numeric($data)) {
        if (is_float($data)) {
            return (float) $data;
        } else {
            return (int) $data;
        }
    } else if (is_string($data)) {
        return mb_convert_encoding($data, $to_charset, $from_charset);
    } else if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = to_charset($value, $to_charset, $from_charset);
        }
    } else if (is_object($data)) {
        foreach ($data as $key => $value) {
            $data->$key = to_charset($value, $to_charset, $from_charset);
        }
    }
    return $data;
}


/**
 * Load a json file and return a php array of the content
 *
 * @param  string $file the json filename
 * @return string|array error string or data array
 */
function json_load($file)
{
    $data = [];
    if (file_exists($file)) {
        $data = to_charset(file_get_contents($file));
        $data = json_decode(
            mb_convert_encoding($data, "UTF-8", "auto"), true, 512,
            JSON_OBJECT_AS_ARRAY || JSON_BIGINT_AS_STRING
        );
    }
    if (null === $data) {
        return json_last_error_msg();
    }
    if (is_array($data)) {
        $data = to_charset($data);
    }
    return $data;
}


/**
 * Fetch all tweets from a twitter dump json file as a php array
 *
 * @param  string $dir to search
 * @param  string $filename the filename containing the tweets
 * @return mixed string error or array $data
 */
function json_load_twitter($dir, $filename)
{
    $files = files_js($dir);
    $data  = to_charset(file_get_contents($files[$filename]));

    // the twitter export file tweet.js begins with:
    // window.YTD.tweet.part0 = [ {
    if ('window' == substr($data, 0, 6) || 'Grailbird' == substr($data, 0, 9)) {
        $data = substr($data, strpos($data, '['));
    }

    $data = json_decode($data, true, 512,
        JSON_OBJECT_AS_ARRAY || JSON_BIGINT_AS_STRING);

    if (empty($data)) {
        return json_last_error_msg();
    }

    return $data;
}


/**
 * Save data array to a json
 *
 * @param  string $file the json filename
 * @param  array $data data to save
 * @param  string optional $prepend string to prepend in the file
 * @param  string optional $append string to append to the file
 * @return boolean true|string TRUE if success or string error message
 */
function json_save($file, $data, $prepend = '', $append = '')
{
    if (TEST) {
        return true;
    }
    if (empty($data)) {
        return 'No data to write to file.';
    }
    if (is_array($data)) {
        $data = to_charset($data);
    }
    if (!file_put_contents($file,
            $prepend . json_encode($data, JSON_PRETTY_PRINT) . $append)) {
        $error = json_last_error_msg();
        if (empty($error)) {
            $error = sprintf("Unknown Error writing file: '%s' (Prepend: '%s', Append: '%s')",
                $file, $prepend, $append);
        }
        return $error;
    }
    return true;
}


/**
 * Load a serialized php data file and return it
 *
 * @param  string $file the json filename
 * @return array $data
 */
function serialize_load($file)
{
    if (0 === filesize($file)) {
        return 'File is empty.';
    }
    if (file_exists($file)) {
        $data = unserialize(file_get_contents($file));
    }
    return empty($data) ? [] : $data;
}


/**
 * Save data array to a php serialized data
 *
 * @param  string $file the json filename
 * @param  mixed $data data to save
 * @return boolean result
 */
function serialize_save($file, $data)
{
    if (TEST) {
        return true;
    }
    if (empty($data)) {
        return 'No data to write to file.';
    }
    return file_put_contents($file, serialize($data));
}


/**
 * unshorten a URL/find the target of a URL
 *
 * @param  string  $url     the url to url_resolve
 * @param  array   $options options
 * @return string|int actual string URL of destination url OR curl status code
 * @see    https://ec.haxx.se/usingcurl-returns.html
 */
function url_resolve($url, $options = [])
{
    if (OFFLINE) {
        return false;
    }

    $commands = get_commands();
    $wget     = $commands['wget'];
    $curl     = $commands['curl'];

    // retry getting a url if the curl exit code is in this list
    // https://ec.haxx.se/usingcurl-returns.html
    // 6 - Couldn't resolve$ host
    $cmds['curl']['retry_exit_codes'] = [
        4, 5, 16, 23, 26, 27, 33, 42, 43,
        45, 48, 55, 59, 60, 61, 75, 76, 77, 78, 80
    ];

    // return codes from curl (url_resolve() function below) which indiciate we should not try to resolve a url
    // -22 signifies a wget failure, the rest are from curl
    $cmds['curl']['dead_exit_codes'] = [3, 6, 7, 18, 28, 35, 47, 52, 56, -22];

    static $urls = []; // remember previous urls
    static $i;
    url_resolve_recheck: // re-check from here
    if (array_key_exists($url, $urls)) {
        if (!in_array($urls[$url], $cmds['curl']['retry_exit_codes'])) {
            return $urls[$url];
        } else if (!in_array($urls[$url], $cmds['curl']['dead_exit_codes'])) {
            return $urls[$url];
        }
        unset($urls[$url]);
    }
    $i++;
    $timeout          = !empty($options['timeout']) ? (int) $options['timeout'] : 6;
    $max_time         = !empty($options['max_time']) ? (int) $options['max_time']
            : $timeout * 3;
    $timeout          = "--connect-timeout $timeout --max-time $max_time";
    $user_agent       = '-A ' . escapeshellarg('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/69.0.3497.100 Safari/537.36');
    $curl_options     = "$user_agent $timeout --ciphers ALL -k";
    $curl_url_resolve = "$curl $curl_options -I -i -Ls -w %{url_effective} -o /dev/null " . escapeshellarg($url);
    $output           = [];
    $target_url       = exec($curl_url_resolve, $output, $status);
    if ($status !== 0) {
        if (!is_numeric($target_url) && $target_url !== $url) {
            $url = $target_url;
            goto url_resolve_recheck;
        }
    }
    // same URl, loop!
    if ($target_url == $url) {
        $cmd_wget_spider = sprintf(
            "$wget --user-agent=$user_agent -t 3 -T 5 -v --spider %s",
            escapeshellarg($url)
        );
        // try wget instead
        $output          = shell_execute($cmd_wget_spider);
        if (!empty($output) && is_array($output)) {
            if (empty($output['stdin']) && empty($output['stdout']) && !empty($output['stderr'])) {
                if (false !== stristr($output['stderr'], 'broken link')) {
                    return -22;
                } else {
                    if (false !== stristr(
                            $output['stderr'],
                            'Remote file exists and could contain further links,'
                        )
                    ) {
                        return $target_url;
                    } else if (preg_match_all(
                            '/(?P<url>http[s]?:\/\/[^\s]+[^\.\s]+)/i',
                            $output['stderr'], $matches
                        )
                    ) {
                        // no URLs found
                        if (!empty($matches['url'])) {
                            foreach ($matches['url'] as $url) {
                                $found_urls[$url] = $url;
                            }
                        }
                        if (!empty($found_urls)) {
                            if ($target_url !== $url) {
                                $target_url = array_pop($found_urls);
                                $url        = $target_url;
                                goto url_resolve_recheck;
                            }
                        }
                    }
                }
            }
        }
    }
    if ($status === 0 || ($status == 6 && !is_numeric($target_url)) && !empty($target_url)) {
        $curl_http_status = "$curl $curl_options -s -o /dev/null -w %{http_code} " . escapeshellarg($target_url);
        $output           = [];
        exec($curl_http_status, $output, $status);
    }
    $return     = ($status === 0) ? $target_url : $status;
    $urls[$url] = $return; // cache in static var
    return $return;
}


/**
 * Download a file URL and save as a given filename using 'wget'
 *
 * @param string $url the url to fetch the file from
 * @param string $path the full path to the filename to save the download as
 * @return boolean|string true if success, false or string $stderr
 */
function url_download($url, $path)
{
    if (OFFLINE) {
        return "Can't download '$url' to '$path' because in OFFLINE mode.";
    }

    // check that it's a URL
    $parts = parse_url($url);
    if (empty($parts) || !is_array($parts) || !array_key_exists('path', $parts)) {
        return false;
    }

    $commands = get_commands();
    $wget     = $commands['wget'];
    $cmd      = "$wget --user-agent='' --verbose -t 3 -T 7 -nc %s -O %s"; // wget args to sprintf to fetch a url and save as a file
    // remove if zero-byte file because wget will just skip otherwise
    if (file_exists($path) && 0 === filesize($path)) {
        unlink($path);
    }

    // fetch the file
    $download = sprintf($cmd, escapeshellarg($url), escapeshellarg($path));
    if (VERBOSE) {
        debug("Downloading: $download");
    }
    $results = shell_execute($download);

    // failed
    if (!file_exists($path)) {
        return false;
    }

    // remove if zero-byte file
    if (0 === filesize($path)) {
        unlink($path);
        return false;
    }

    // success text
    if (false !== stristr($results['stderr'], 'already there; not retrieving') ||
        false !== stristr($results['stderr'], 'saved')) {
        return true;
    }

    // return std err if not success
    return $results['stderr'];
}


/**
 * Download an image URL from twitpic.com and save as a given filename using 'wget'
 *
 * @param string $url the url to fetch the file from
 * @param string $path the full path to the filename to save the download as
 * @param string $mime_type mime_type to check downloaded image for
 * @return boolean|string true if success, false or string $stderr
 */
function twitpic_download($url, $path, $mime_type = 'image/jpeg')
{
    if (OFFLINE) {
        return "Can't download '$url' to '$path' because in OFFLINE mode.";
    }

    $commands = get_commands();
    $wget     = $commands['wget'];
    $convert  = $commands['convert'];
    $gunzip   = $commands['gunzip'];
    $grep     = $commands['grep'];
    $cut      = $commands['cut'];
    $xargs    = $commands['xargs'];

    // fetch html from twitpic and grep the image file url to STDOUT
    $cmd = "$wget --user-agent='' --verbose -t 3 -T 7 -O- %s | $gunzip -c | $grep 'img src='  | $cut -d '\"' -f 2 | $xargs";

    // wget args to sprintf to fetch a url and save as a file
    // remove if zero-byte file because wget will just skip otherwise
    if (file_exists($path) && 0 === filesize($path)) {
        unlink($path);
    }

    // fetch the file
    $fetch_url = sprintf($cmd, escapeshellarg($url));
    if (VERBOSE) {
        debug("Fetching URL from twitpic: $fetch_url");
    }
    $results = shell_execute($fetch_url);

    // check the STDOUT is valid
    if (empty($results['stdout'])) {
        return false;
    }
    $url = trim($results['stdout']);
    debug($url);

    // download directly
    $results = url_download($url, $path);
    if (true !== $results) {
        return $results;
    }

    // at this point we have the file, finally
    // convert if wrong file type for extension
    $type = mime_content_type($path);
    if ($mime_type !== $type) {
        debug(sprintf("Convert-ing file from '%s' to '%'", $type, $mime_type));
        // as long as the extension is correct, convert will convert incorrect image format by extension
        // i.e. a PNG with extension JPG will be converted to JPG by doing 'convert file.jpg file.jpg'
        $file_convert = sprintf(
            "%s %s %s", $convert, escapeshellarg($path), escapeshellarg($path)
        );
        $results      = shell_execute($file_convert);
        if (false === $results) {
            return false;
        } else if (is_array($results)) {
            debug("Results:", $results);
        }
    }

    return true;
}

