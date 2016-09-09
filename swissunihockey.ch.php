<?php

/**
 * Plugin Name: swissunihockey.ch
 * Plugin URI: http://www.mahendrakalkura.com
 * Description: ...coming soon...
 * Author: Mahendra Kalkura
 * Version: 1.0
 * Author URI: http://www.mahendrakalkura.com
 */

require sprintf('%svendor/autoload.php', plugin_dir_path(__FILE__));

$options = array(
    'path' => sprintf('%s.cache', plugin_dir_path(__FILE__)),
);
$driver = new Stash\Driver\FileSystem($options);

$pool = new Stash\Pool($driver);

$GLOBALS['swissunihockey.ch'] = array(
    'pool' => $pool,
);

function swissunihockey_ch_usort_leagues($a, $b)
{
    if ($a['text'] === $b['text']) {
        return 0;
    }
    return ($a['text'] < $b['text'])? -1 : 1;
}

function swissunihockey_ch_usort_seasons($a, $b)
{
    if ($a['text'] === $b['text']) {
        return 0;
    }
    return ($a['text'] < $b['text'])? -1 : 1;
}

function swissunihockey_ch_usort_clubs($a, $b)
{
    if ($a['text'] === $b['text']) {
        return 0;
    }
    return ($a['text'] < $b['text'])? -1 : 1;
}

function swissunihockey_ch_usort_teams($a, $b)
{
    if ($a['text'] === $b['text']) {
        return 0;
    }
    return ($a['text'] < $b['text'])? -1 : 1;
}

function swissunihockey_ch_usort_table($a, $b)
{
    if ($a['rank'] === $b['rank']) {
        return 0;
    }
    return ($a['rank'] < $b['rank'])? -1 : 1;
}

function swissunihockey_ch_usort_games($a, $b)
{
    if ($a['timestamp'] === $b['timestamp']) {
        return 0;
    }
    return ($a['timestamp'] < $b['timestamp'])? -1 : 1;
}

function swissunihockey_ch_get_leagues()
{
    $item = $GLOBALS['swissunihockey.ch']['pool']->getItem('leagues');
    $leagues = $item->get();
    if ($item->isMiss()) {
        $leagues = array();
        $response = wp_remote_get(
            'https://api-v2.swissunihockey.ch/api/leagues'
        );
        $body = json_decode($response['body'], true);
        foreach ((array) $body['entries'] as $entry) {
            $leagues[] = array(
                'league' => (string) $entry['set_in_context']['league'],
                'game_class' => (string) $entry['set_in_context']['game_class'],
                'text' => $entry['text'],
            );
        }
        usort($leagues, 'swissunihockey_ch_usort_leagues');
        $item->set($leagues);
        $item->expiresAfter(864000);
        $GLOBALS['swissunihockey.ch']['pool']->save($item);
    }

    return $leagues;
}

function swissunihockey_ch_get_seasons()
{
    $item = $GLOBALS['swissunihockey.ch']['pool']->getItem('seasons');
    $seasons = $item->get();
    if ($item->isMiss()) {
        $seasons = array();
        $response = wp_remote_get(
            'https://api-v2.swissunihockey.ch/api/seasons'
        );
        $body = json_decode($response['body'], true);
        foreach ((array) $body['entries'] as $entry) {
            $seasons[] = array(
                'season' => (string) $entry['set_in_context']['season'],
                'text' => $entry['text'],
            );
        }
        usort($seasons, 'swissunihockey_ch_usort_seasons');
        $item->set($seasons);
        $item->expiresAfter(864000);
        $GLOBALS['swissunihockey.ch']['pool']->save($item);
    }

    return $seasons;
}

function swissunihockey_ch_get_clubs()
{
    $item = $GLOBALS['swissunihockey.ch']['pool']->getItem('clubs');
    $clubs = $item->get();
    if ($item->isMiss()) {
        $clubs = array();
        $response = wp_remote_get(
            'https://api-v2.swissunihockey.ch/api/clubs'
        );
        $body = json_decode($response['body'], true);
        foreach ((array) $body['entries'] as $entry) {
            $club_id = (string) $entry['set_in_context']['club_id'];
            $clubs[] = array(
                'text' => $entry['text'],
                'club_id' => $club_id,
                'teams' => swissunihockey_ch_get_teams($club_id),
            );
        }
        usort($clubs, 'swissunihockey_ch_usort_clubs');
        $item->set($clubs);
        $item->expiresAfter(864000);
        $GLOBALS['swissunihockey.ch']['pool']->save($item);
    }

    return $clubs;
}

function swissunihockey_ch_get_teams($club_id)
{
    $item = $GLOBALS['swissunihockey.ch']['pool']->getItem(
        sprintf('teams/%s', $club_id)
    );
    $teams = $item->get();
    if ($item->isMiss()) {
        $teams = array();
        $response = wp_remote_get(
            sprintf(
                'https://api-v2.swissunihockey.ch/api/clubs/%s/statistics',
                $club_id
            )
        );
        $body = json_decode($response['body'], true);
        foreach ((array) $body['data']['regions'][0]['rows'] as $row) {
            $teams[] = array(
                'text' => $row['cells'][0]['text'][0],
                'team_id' => (string) $row['team_id'],
            );
        }
        usort($teams, 'swissunihockey_ch_usort_teams');
        $item->set($teams);
        $item->expiresAfter(864000);
        $GLOBALS['swissunihockey.ch']['pool']->save($item);
    }

    return $teams;
}

function swissunihockey_ch_get_table($league, $game_class, $season)
{
    $item = $GLOBALS['swissunihockey.ch']['pool']->getItem(
        sprintf('table/%s/%s/%s', $league, $game_class, $season)
    );
    $table = $item->get();
    if ($item->isMiss()) {
        $table = array();
        $response = wp_remote_get(
            sprintf(
                'https://api-v2.swissunihockey.ch/api/rankings'.
                '?league=%s&game_class=%s&season=%s',
                $league,
                $game_class,
                $season
            )
        );
        $body = json_decode($response['body'], true);
        foreach ((array) $body['data']['regions'][0]['rows'] as $row) {
            $rank = $row['cells'][0]['text'][0];
            if (!$rank) {
                continue;
            }
            $table[] = array(
                'rank' => $rank,
                'logo' => $row['cells'][1]['image']['url'],
                'name' => $row['cells'][2]['text'][0],
                'games' => array(
                    'total' => $row['cells'][3]['text'][0],
                    'wins' => $row['cells'][4]['text'][0],
                    'wins_overtime' => $row['cells'][5]['text'][0],
                    'losses' => $row['cells'][7]['text'][0],
                    'losses_overtime' => $row['cells'][6]['text'][0],
                    'time' => $row['cells'][8]['text'][0],
                    'goals' => $row['cells'][9]['text'][0],
                    'points' => $row['cells'][10]['text'][0],
                ),
            );
        }
        usort($table, 'swissunihockey_ch_usort_table');
        $item->set($table);
        $item->expiresAfter(86400);
        $GLOBALS['swissunihockey.ch']['pool']->save($item);
    }

    return $table;
}

function swissunihockey_ch_get_games($league, $game_class, $season, $round)
{
    $item = $GLOBALS['swissunihockey.ch']['pool']->getItem(
        sprintf('games/%s/%s/%s/%s', $league, $game_class, $season, $round)
    );
    $games = $item->get();
    if ($item->isMiss()) {
        $response = wp_remote_get(
            sprintf(
                'https://api-v2.swissunihockey.ch/api/games'.
                '?league=%s&game_class=%s&season=%s&round=%s&mode=list',
                $league,
                $game_class,
                $season,
                $round
            )
        );
        $body = json_decode($response['body'], true);
        $games = array(
            'round' => array(
                'previous' =>
                    $body['data']['slider']['prev']['set_in_context']['round'],
                'next' =>
                    $body['data']['slider']['next']['set_in_context']['round'],
            ),
            'items' => array(),
        );
        foreach ((array) $body['data']['regions'][0]['rows'] as $row) {
            $game_id = (string) $row['cells'][0]['link']['ids'][0];
            $games['items'][] = swissunihockey_ch_get_game($game_id, false);
        }
        usort($games['items'], 'swissunihockey_ch_usort_games');
        $item->set($games);
        $item->expiresAfter(86400);
        $GLOBALS['swissunihockey.ch']['pool']->save($item);
    }

    return $games;
}

function swissunihockey_ch_get_game($game_id, $with_events)
{
    $item = $GLOBALS['swissunihockey.ch']['pool']->getItem(
        sprintf('game/%s/%s', $game_id, $with_events? '1': '0')
    );
    $game = $item->get();
    if ($item->isMiss()) {
        $response = wp_remote_get(
            sprintf('https://api-v2.swissunihockey.ch/api/games/%s', $game_id)
        );
        $body = json_decode($response['body'], true);
        $row = $body['data']['regions'][0]['rows'][0];
        $date = $row['cells'][5]['text'][0];
        $time = $row['cells'][6]['text'][0];
        $timestamp = strtotime(sprintf('%s %s:00', $date, $time));
        $hours_since_start = $row['debug']['hours_since_start'];
        $status = 'Not yet started';
        if ($hours_since_start > 0.0) {
            if ($hours_since_start > 2.0) {
                $status = 'Finished';
            } else {
                $status = 'Ongoing';
            }
        }
        $referees = array();
        if ($row['cells'][8]['text'][0]) {
            $referees[] = $row['cells'][8]['text'][0];
        }
        if ($row['cells'][9]['text'][0]) {
            $referees[] = $row['cells'][9]['text'][0];
        }
        $referees = implode(' and ', $referees);
        $game = array(
            'id' => (string) $game_id,
            'home' => array(
                'id' => (string) $row['cells'][1]['link']['ids'][0],
                'name' => $row['cells'][1]['text'][0],
                'logo' => $row['cells'][0]['image']['url'],
            ),
            'away' => array(
                'id' => (string) $row['cells'][3]['link']['ids'][0],
                'name' => $row['cells'][3]['text'][0],
                'logo' => $row['cells'][2]['image']['url'],
            ),
            'date' => $date,
            'time' => $time,
            'timestamp' => $timestamp,
            'status' => $status,
            'score' => $row['cells'][4]['text'],
            'location' => $row['cells'][7]['text'][0],
            'referees' => $referees,
            'spectators' => $row['cells'][10]['text'][0],
        );
        if ($with_events) {
            $game_events = swissunihockey_ch_get_game_events($game_id);
            $game['events'] = $game_events;
        }
        $item->set($game);
        $item->expiresAfter(86400);
        $GLOBALS['swissunihockey.ch']['pool']->save($item);
    }

    return $game;
}

function swissunihockey_ch_get_game_events($game_id)
{
    $item = $GLOBALS['swissunihockey.ch']['pool']->getItem(
        sprintf('game_events/%s', $game_id)
    );
    $game = $item->get();
    if ($item->isMiss()) {
        $response = wp_remote_get(
            sprintf(
                'https://api-v2.swissunihockey.ch/api/game_events/%s',
                $game_id
            )
        );
        $body = json_decode($response['body'], true);
        $home = $body['data']['tabs'][1]['text'];
        $game_events = array();
        foreach ((array) $body['data']['regions'][0]['rows'] as $row) {
            $game_events[] = array(
                'time' => $row['cells'][0]['text'][0],
                'event' => $row['cells'][1]['text'][0],
                'team' => $row['cells'][2]['text'][0],
                'player' => $row['cells'][3]['text'][0],
            );
        }
        $item->set($game_events);
        $item->expiresAfter(60);
        $GLOBALS['swissunihockey.ch']['pool']->save($item);
    }

    return $game_events;
}

function swissunihockey_ch_get_url($array)
{
    $url = $_SERVER['REQUEST_URI'];
    $url = remove_query_arg(
        array(
            'league',
            'game_class',
            'season',
            'team_id',
            'round',
            'game_id',
        ),
        $url
    );
    $url = add_query_arg($array, $url);

    return $url;
}

function swissunihockey_ch_init()
{
    add_action('wp_enqueue_scripts', 'swissunihockey_ch_scripts');
    add_action('wp_enqueue_scripts', 'swissunihockey_ch_styles');
}

function swissunihockey_ch_admin_init()
{
    add_action('admin_print_scripts', 'swissunihockey_ch_scripts');
    add_action('admin_print_styles', 'swissunihockey_ch_styles');
}

function swissunihockey_ch_scripts()
{
    wp_enqueue_script(
        'all_js',
        sprintf('%s/swissunihockey.ch.js', plugins_url('/swissunihockey.ch')),
        array('jquery')
    );
}

function swissunihockey_ch_styles()
{
    wp_enqueue_style(
        'all_css',
        sprintf('%s/swissunihockey.ch.css', plugins_url('/swissunihockey.ch'))
    );
}

function swissunihockey_ch_admin_menu()
{
    add_menu_page(
        'swissunihockey.ch',
        'swissunihockey.ch',
        'manage_options',
        '/swissunihockey.ch',
        'swissunihockey_ch_options',
        ''
    );
    add_submenu_page(
        '/swissunihockey.ch',
        'Options',
        'Options',
        'manage_options',
        '/swissunihockey.ch',
        'swissunihockey_ch_options'
    );
    add_submenu_page(
        '/swissunihockey.ch',
        'F.A.Q.',
        'F.A.Q.',
        'manage_options',
        '/swissunihockey.ch/faq',
        'swissunihockey_ch_faq'
    );
}

function swissunihockey_ch_flashes()
{
    ?>
    <?php if (!empty($_SESSION['swissunihockey.ch']['flashes'])) : ?>
        <?php foreach (
            $_SESSION['swissunihockey.ch']['flashes'] AS $key => $value
        ) : ?>
            <div class="<?php echo $key; ?>">
                <p><strong><?php echo $value; ?></strong></p>
            </div>
        <?php endforeach; ?>
        <?php $_SESSION['swissunihockey.ch']['flashes'] = array(); ?>
    <?php endif; ?>
    <?php
}

function swissunihockey_ch_options()
{
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permissions to access this page.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        update_option(
            'swissunihockey_ch_league_game_class',
            $_REQUEST['league_game_class'],
            true
        );
        update_option(
            'swissunihockey_ch_season',
            $_REQUEST['season'],
            true
        );
        update_option(
            'swissunihockey_ch_team_id',
            $_REQUEST['team_id'],
            true
        );
        $_SESSION['swissunihockey.ch']['flashes'] = array(
            'updated' => 'Your options were updated successfully.',
        );
        ?>
        <meta
            content="0;url=<?php echo admin_url(
                'admin.php?page=swissunihockey.ch'
            ); ?>"
            http-equiv="refresh"
            >
        <?php
        die();
    }

    $league_game_class = get_option('swissunihockey_ch_league_game_class');
    $season = get_option('swissunihockey_ch_season');
    $team_id = get_option('swissunihockey_ch_team_id');

    $leagues = swissunihockey_ch_get_leagues();
    $seasons = swissunihockey_ch_get_seasons();
    $clubs = swissunihockey_ch_get_clubs();
    ?>
    <div class="swissunihockey-ch">
        <h2>swissunihockey.ch :: Options</h2>
        <?php swissunihockey_ch_flashes(); ?>
        <form
            action="<?php echo admin_url(
                'admin.php?page=swissunihockey.ch'
            ); ?>"
            enctype="multipart/form-data"
            method="post"
            >
            <table class="bordered widefat wp-list-table">
                <tr>
                    <td class="narrow">
                        <label for="league_game_class">Default League</label>
                    </td>
                    <td>
                        <select id="league_game_class" name="league_game_class">
                            <?php foreach ($leagues as $league) : ?>
                                <?php
                                $value = sprintf(
                                    '%s-%s',
                                    $league['league'],
                                    $league['game_class']
                                );
                                ?>
                                <option
                                    <?php if ($league_game_class === $value) : ?>
                                        selected="selected"
                                    <?php endif; ?>
                                    value="<?php echo $value; ?>"
                                    >
                                    <?php
                                    echo sprintf(
                                        '%s (%s/%s)',
                                        $league['text'],
                                        $league['league'],
                                        $league['game_class']
                                    );
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class="narrow">
                        <label for="season">Default Season</label>
                    </td>
                    <td>
                        <select id="season" name="season">
                            <?php foreach ($seasons as $s) : ?>
                                <option
                                    <?php if ($season === $s['season']) : ?>
                                        selected="selected"
                                    <?php endif; ?>
                                    value="<?php echo $s['season']; ?>"
                                    >
                                    <?php echo $s['text'];?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class="narrow">
                        <label for="team_id">Default Club/Team</label>
                    </td>
                    <td>
                        <select id="team_id" name="team_id">
                            <option value="0">All Teams</option>
                            <?php foreach ($clubs as $club) : ?>
                                <optgroup label="<?php echo $club['text'];?>">
                                    <?php foreach ($club['teams'] as $team) : ?>
                                        <option
                                            <?php if ($team_id === $team['team_id']) : ?>
                                                selected="selected"
                                            <?php endif; ?>
                                            value="<?php echo $team['team_id']; ?>"
                                            >
                                            <?php echo $team['text'];?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input class="button-primary" type="submit" value="Save">
            </p>
        </form>
    </div>
    <?php
}

function swissunihockey_ch_faq()
{
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permissions to access this page.');
    }

    ?>
    <div class="swissunihockey-ch">
        <h2>swissunihockey.ch :: Frequently Asked Questions</h2>
        <div class="welcome-panel">
            <h2>How to obtain a <strong>shortcode?</strong></h2>
            <hr>
            <p>
                You can obtain a <strong>shortcode</strong> by embedding the
                following text in your page(s)/post(s) :
            </p>
            <pre>[swissunihockey_ch]</pre>
        </div>
    </div>
    <?php
}

function swissunihockey_ch_shortcode()
{
    $pane = $_REQUEST['pane']? $_REQUEST['pane']: '';
    if ($pane !== '1' and $pane !== '2' and $pane !== '3') {
        $pane = '1';
    }
    if ($pane === '1') {
        swissunihockey_ch_shortcode_1(
            $_REQUEST['league']? $_REQUEST['league']: '',
            $_REQUEST['game_class']? $_REQUEST['game_class']: '',
            $_REQUEST['season']? $_REQUEST['season']: '',
            $_REQUEST['team_id']? $_REQUEST['team_id']: '',
            $_REQUEST['round']? $_REQUEST['round']: ''
        );
    }
    if ($pane === '2') {
        swissunihockey_ch_shortcode_2(
            $_REQUEST['league']? $_REQUEST['league']: '',
            $_REQUEST['game_class']? $_REQUEST['game_class']: '',
            $_REQUEST['season']? $_REQUEST['season']: '',
            $_REQUEST['team_id']? $_REQUEST['team_id']: '',
            $_REQUEST['round']? $_REQUEST['round']: '',
            $_REQUEST['game_id']? $_REQUEST['game_id']: ''
        );
    }
    if ($pane === '3') {
        swissunihockey_ch_shortcode_3(
            $_REQUEST['league']? $_REQUEST['league']: '',
            $_REQUEST['game_class']? $_REQUEST['game_class']: '',
            $_REQUEST['season']? $_REQUEST['season']: '',
            $_REQUEST['team_id']? $_REQUEST['team_id']: '',
            $_REQUEST['round']? $_REQUEST['round']: ''
        );
    }
}

function swissunihockey_ch_shortcode_1(
    $league,
    $game_class,
    $season,
    $team_id,
    $round
)
{
    if (!$league or !$game_class) {
        $league_game_class = $_REQUEST['league_game_class']?
            $_REQUEST['league_game_class']:
            get_option('swissunihockey_ch_league_game_class');
        list($league, $game_class) = explode('-', $league_game_class);
    }

    if (!$season) {
        $season = $_REQUEST['season']?
            $_REQUEST['season']:
            get_option('swissunihockey_ch_season');
    }

    if (!$team_id) {
        $team_id = $_REQUEST['team_id']?
            $_REQUEST['team_id']:
            get_option('swissunihockey_ch_team_id');
    }

    $leagues = swissunihockey_ch_get_leagues();
    $seasons = swissunihockey_ch_get_seasons();
    $clubs = swissunihockey_ch_get_clubs();

    $games = swissunihockey_ch_get_games(
        $league, $game_class, $season, $round
    );

    if ($team_id !== '' and $team_id !== '-1') {
        $items = array();
        if ($games['items']) {
            foreach ($games['items'] as $item) {
                if (
                    $team_id === $item['home']['id'] or
                    $team_id === $item['away']['id']
                ) {
                    $items[] = $item;
                }
            }
        }
        $games['items'] = $items;
    }

    $url = array(
        'pane' => '3',
        'league' => $league,
        'game_class' => $game_class,
        'season' => $season,
        'team_id' => $team_id,
        'round' => $round,
    );
    ?>
    <link
        href="<?php echo plugins_url(
            '/swissunihockey.ch'
        ); ?>/vendor/font-awesome/css/font-awesome.css"
        rel="stylesheet"
        >
    <link
        href="<?php echo plugins_url(
            '/swissunihockey.ch'
        ); ?>/swissunihockey.ch.css"
        rel="stylesheet"
        >
    <script
        src="<?php echo plugins_url(
            '/swissunihockey.ch'
        ); ?>/swissunihockey.ch.js"
        ></script>
    <div class="swissunihockey-ch">
        <form
            action="<?php echo swissunihockey_ch_get_url(
                array(
                    'pane' => '1',
                )
            ); ?>"
            enctype="multipart/form-data"
            method="post"
            >
            <table class="table">
                <tr>
                    <td>
                        <label for="league_game_class">League</label>
                    </td>
                    <td>
                        <select id="league_game_class" name="league_game_class">
                            <?php foreach ($leagues as $l) : ?>
                                <?php
                                $value = sprintf(
                                    '%s-%s', $l['league'], $l['game_class']
                                );
                                ?>
                                <option
                                    <?php if ($league_game_class === $value) : ?>
                                        selected="selected"
                                    <?php endif; ?>
                                    value="<?php echo $value; ?>"
                                    >
                                    <?php
                                    echo sprintf(
                                        '%s (%s/%s)',
                                        $l['text'],
                                        $l['league'],
                                        $l['game_class']
                                    );
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="season">Season</label>
                    </td>
                    <td>
                        <select id="season" name="season">
                            <?php foreach ($seasons as $s) : ?>
                                <option
                                    <?php if ($season === $s['season']) : ?>
                                        selected="selected"
                                    <?php endif; ?>
                                    value="<?php echo $s['season']; ?>"
                                    >
                                    <?php echo $s['text'];?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class="narrow">
                        <label for="team_id">Default Club/Team</label>
                    </td>
                    <td>
                        <select id="team_id" name="team_id">
                            <option value="0">All Teams</option>
                            <?php foreach ($clubs as $club) : ?>
                                <optgroup label="<?php echo $club['text'];?>">
                                    <?php foreach ($club['teams'] as $team) : ?>
                                        <option
                                            <?php if ($team_id === $team['team_id']) : ?>
                                                selected="selected"
                                            <?php endif; ?>
                                            value="<?php echo $team['team_id']; ?>"
                                            >
                                            <?php echo $team['text'];?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>
                        <a
                            href="<?php echo swissunihockey_ch_get_url($url); ?>"
                            >Table</a>
                    </td>
                    <td class="text-right">
                        <input
                            class="button-primary"
                            type="submit"
                            value="Change"
                            >
                    </td>
                </tr>
            </table>
        </form>
        <p class="text-right">
            <?php if ($games['round']['previous']) : ?>
                <a
                    class="pull-left"
                    href="<?php echo swissunihockey_ch_get_url(
                        array(
                            'pane' => '1',
                            'league' => $league,
                            'game_class' => $game_class,
                            'season' => $season,
                            'team_id' => $team_id,
                            'round' => $games['round']['previous'],
                        )
                    ); ?>"
                    >Previous</a>
            <?php else: ?>
                <span class="pull-left line-through">Previous</span>
            <?php endif; ?>
            <?php if ($games['round']['next']) : ?>
                <a
                    href="<?php echo swissunihockey_ch_get_url(
                        array(
                            'pane' => '1',
                            'league' => $league,
                            'game_class' => $game_class,
                            'season' => $season,
                            'team_id' => $team_id,
                            'round' => $games['round']['next'],
                        )
                    ); ?>"
                    >Next</a>
            <?php else: ?>
                <span class="line-through">Next</span>
            <?php endif; ?>
        </p>
        <?php if ($games['items']) : ?>
            <table class="table">
                <?php foreach ($games['items'] as $game) : ?>
                    <?php
                    $status = $game['status'];
                    $url = swissunihockey_ch_get_url(
                        array(
                            'pane' => '2',
                            'league' => $league,
                            'game_class' => $game_class,
                            'season' => $season,
                            'team_id' => $team_id,
                            'round' => $round,
                            'game_id' => $game['id'],
                        )
                    );
                    ?>
                    <tr>
                        <td class="text-center">
                            <a href="<?php echo $url; ?>">
                                <?php echo $game['date'] . ' ' . $game['time']; ?>
                            </a>
                        </td>
                        <td class="text-center">
                            <a href="<?php echo $url; ?>">
                                <?php echo $game['location']; ?>
                            </a>
                        </td>
                        <td class="text-center">
                            <a href="<?php echo $url; ?>">
                                <?php echo $game['home']['name']; ?>
                            </a>
                        </td>
                        <td class="text-center">
                            <a
                                href="<?php echo $url; ?>"
                                title="<?php echo $game['home']['name']; ?>"
                                >
                                <img
                                    src="<?php echo $game['home']['logo']; ?>"
                                    >
                            </a>
                        </td>
                        <td class="text-center">
                            <a
                                href="<?php echo $url; ?>"
                                title="<?php echo $game['away']['name']; ?>"
                                >
                                <img
                                    src="<?php echo $game['away']['logo']; ?>"
                                    >
                            </a>
                        </td>
                        <td class="text-center">
                            <a href="<?php echo $url; ?>">
                                <?php echo $game['away']['name']; ?>
                            </a>
                        </td>
                        <td class="text-center">
                            <a href="<?php echo $url; ?>">
                                <?php echo $game['score'][0]; ?>
                            </a>
                        </td>
                        <td class="text-center">
                            <a
                                class="icon" href="<?php echo $url; ?>"
                                >
                                <?php if ($status === 'Not yet started') : ?>
                                    <i class="fa fa-clock-o"></i>
                                <?php endif; ?>
                                <?php if ($status === 'Ongoing') : ?>
                                    <i class="fa fa-bullhorn"></i>
                                <?php endif; ?>
                                <?php if ($status === 'Finished') : ?>
                                    <i class="fa fa-check"></i>
                                <?php endif; ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p>There are no games scheduled in the selected time period.</p>
        <?php endif; ?>
    </div>
    <?php
}

function swissunihockey_ch_shortcode_2(
    $league,
    $game_class,
    $season,
    $team_id,
    $round,
    $game_id
) {
    $game = swissunihockey_ch_get_game($game_id, true);
    ?>
    <div class="swissunihockey-ch">
        <p>
            <a
                href="<?php
                echo swissunihockey_ch_get_url(
                    array(
                        'pane' => '1',
                        'league' => $league,
                        'game_class' => $game_class,
                        'season' => $season,
                        'team_id' => $team_id,
                        'round' => $round,
                    )
                );
                ?>"
                >Back</a>
        </p>
        <table class="table">
            <tr>
                <td class="text-center">
                    <img
                        alt="<?php echo $game['home']['name']; ?>"
                        src="<?php echo $game['home']['logo']; ?>"
                        >
                        <br><?php echo $game['home']['name']; ?>
                </td>
                <td class="text-center" width="50%">
                    <h1><?php echo $game['score'][0]; ?></h1>
                    <?php echo $game['score'][1]; ?>
                    <p>
                        <?php echo $game['date'] . ' ' . $game['time']; ?>
                        <br>
                        <?php echo $game['location']; ?>
                        <br>
                        <?php echo $game['referees']; ?>
                        <br>
                        <?php echo $game['spectators']; ?>
                    </p>
                </td>
                <td class="text-center">
                    <img
                        alt="<?php echo $game['away']['name']; ?>"
                        src="<?php echo $game['away']['logo']; ?>"
                        >
                        <br><?php echo $game['away']['name']; ?>
                </td>
            </tr>
            <?php foreach ($game['events'] as $event) : ?>
                <tr>
                    <td class="text-center">
                        <?php echo $event['time']; ?>
                        <?php echo $event['event']; ?>
                    </td>
                    <td class="text-center">
                        <?php echo $event['team']; ?>
                    </td>
                    <td class="text-center">
                        <?php echo $event['player']; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php
}

function swissunihockey_ch_shortcode_3(
    $league,
    $game_class,
    $season,
    $team_id,
    $round
) {
    $table = swissunihockey_ch_get_table($league, $game_class, $season);
    ?>
    <div class="swissunihockey-ch">
        <p>
            <a
                href="<?php
                echo swissunihockey_ch_get_url(
                    array(
                        'pane' => '1',
                        'league' => $league,
                        'game_class' => $game_class,
                        'season' => $season,
                        'team_id' => $team_id,
                        'round' => $round,
                    )
                );
                ?>"
                >Back</a>
        </p>
        <table class="table">
            <tr>
                <th class="text-right">Rg.</th>
                <th class="text-center" colspan="2">Team</th>
                <th class="text-right">Sp</th>
                <th class="text-right">S</th>
                <th class="text-right">SnV</th>
                <th class="text-right">NnV</th>
                <th class="text-right">N</th>
                <th class="text-right">T</th>
                <th class="text-right">TD</th>
                <th class="text-right">P</th>
            </tr>
            <?php foreach ($table as $row) : ?>
                <tr>
                    <td class="text-right">
                        <?php echo $row['rank']; ?>
                    </td>
                    <td class="text-center">
                        <img src="<?php echo $row['logo']; ?>">
                    </td>
                    <td class="text-center">
                        <?php echo $row['name']; ?>
                    </td>
                    <td class="text-right">
                        <?php echo $row['games']['total']; ?>
                    </td>
                    <td class="text-right">
                        <?php echo $row['games']['wins']; ?>
                    </td>
                    <td class="text-right">
                        <?php echo $row['games']['wins_overtime']; ?>
                    </td>
                    <td class="text-right">
                        <?php echo $row['games']['losses_overtime']; ?>
                    </td>
                    <td class="text-right">
                        <?php echo $row['games']['losses']; ?>
                    </td>
                    <td class="text-right">
                        <?php echo $row['games']['time']; ?>
                    </td>
                    <td class="text-right">
                        <?php echo $row['games']['goals']; ?>
                    </td>
                    <td class="text-right">
                        <?php echo $row['games']['points']; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php
}

add_action('init', 'swissunihockey_ch_init');

add_action('admin_init', 'swissunihockey_ch_admin_init');
add_action('admin_menu', 'swissunihockey_ch_admin_menu');

add_shortcode('swissunihockey_ch', 'swissunihockey_ch_shortcode');
