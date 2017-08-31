<?php

/**
 * Plugin Name: mlFloorball
 * Plugin URI: http://medialeg.ch/produkte/mlfloorball/
 * Description: ...coming soon...
 * Author: medialeg
 * Version: 1.0
 * Author URI: http://medialeg.ch/Products
 */

require sprintf('%svendor/autoload.php', plugin_dir_path(__FILE__));

$options = array(
    'path' => sprintf('%s.cache', plugin_dir_path(__FILE__)),
);
$driver = new Stash\Driver\FileSystem($options);

$pool = new Stash\Pool($driver);

$GLOBALS['mlFloorball'] = array(
    'pool' => $pool,
);

function mlFloorball_usort_leagues($a, $b)
{
    if ($a['text'] === $b['text']) {
        return 0;
    }
    return ($a['text'] < $b['text'])? -1 : 1;
}

function mlFloorball_usort_seasons($a, $b)
{
    if ($a['text'] === $b['text']) {
        return 0;
    }
    return ($a['text'] < $b['text'])? -1 : 1;
}

function mlFloorball_usort_clubs($a, $b)
{
    if ($a['text'] === $b['text']) {
        return 0;
    }
    return ($a['text'] < $b['text'])? -1 : 1;
}

function mlFloorball_usort_teams($a, $b)
{
    if ($a['text'] === $b['text']) {
        return 0;
    }
    return ($a['text'] < $b['text'])? -1 : 1;
}

function mlFloorball_usort_table($a, $b)
{
    if ($a['rank'] === $b['rank']) {
        return 0;
    }
    return ($a['rank'] < $b['rank'])? -1 : 1;
}

function mlFloorball_usort_games($a, $b)
{
    if ($a['timestamp'] === $b['timestamp']) {
        return 0;
    }
    return ($a['timestamp'] < $b['timestamp'])? -1 : 1;
}

function mlFloorball_get_leagues()
{
    $item = $GLOBALS['mlFloorball']['pool']->getItem('leagues');
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
        usort($leagues, 'mlFloorball_usort_leagues');
        $item->set($leagues);
        $item->expiresAfter(864000);
        $GLOBALS['mlFloorball']['pool']->save($item);
    }

    return $leagues;
}

function mlFloorball_get_seasons()
{
    $item = $GLOBALS['mlFloorball']['pool']->getItem('seasons');
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
        usort($seasons, 'mlFloorball_usort_seasons');
        $item->set($seasons);
        $item->expiresAfter(864000);
        $GLOBALS['mlFloorball']['pool']->save($item);
    }

    return $seasons;
}

function mlFloorball_get_clubs()
{
    $item = $GLOBALS['mlFloorball']['pool']->getItem('clubs');
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
                'teams' => mlFloorball_get_teams($club_id),
            );
        }
        usort($clubs, 'mlFloorball_usort_clubs');
        $item->set($clubs);
        $item->expiresAfter(864000);
        $GLOBALS['mlFloorball']['pool']->save($item);
    }

    return $clubs;
}

function mlFloorball_get_teams($club_id)
{
    $item = $GLOBALS['mlFloorball']['pool']->getItem(
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
        usort($teams, 'mlFloorball_usort_teams');
        $item->set($teams);
        $item->expiresAfter(864000);
        $GLOBALS['mlFloorball']['pool']->save($item);
    }

    return $teams;
}

function mlFloorball_get_table($league, $game_class, $season)
{
    $item = $GLOBALS['mlFloorball']['pool']->getItem(
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
        usort($table, 'mlFloorball_usort_table');
        $item->set($table);
        $item->expiresAfter(86400);
        $GLOBALS['mlFloorball']['pool']->save($item);
    }

    return $table;
}

function mlFloorball_get_games($league, $game_class, $season, $round)
{
    $item = $GLOBALS['mlFloorball']['pool']->getItem(
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
            $games['items'][] = mlFloorball_get_game($game_id, false);
        }
        usort($games['items'], 'mlFloorball_usort_games');
        $item->set($games);
        $item->expiresAfter(86400);
        $GLOBALS['mlFloorball']['pool']->save($item);
    }

    return $games;
}

function mlFloorball_get_game($game_id, $with_events)
{
    $item = $GLOBALS['mlFloorball']['pool']->getItem(
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
            'league' => $body['data']['subtitle'],
            'time' => $time,
            'timestamp' => $timestamp,
            'status' => $status,
            'score' => $row['cells'][4]['text'],
            'location' => $row['cells'][7]['text'][0],
            'referees' => $referees,
            'spectators' => $row['cells'][10]['text'][0],
        );
        if ($with_events) {
            $game_events = mlFloorball_get_game_events($game_id);
            $game['events'] = $game_events;
        }
        $item->set($game);
        $item->expiresAfter(86400);
        $GLOBALS['mlFloorball']['pool']->save($item);
    }

    return $game;
}

function mlFloorball_get_game_events($game_id)
{
    $item = $GLOBALS['mlFloorball']['pool']->getItem(
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
        $GLOBALS['mlFloorball']['pool']->save($item);
    }

    return $game_events;
}

function mlFloorball_get_url($array)
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

function mlFloorball_init()
{
    add_action('wp_enqueue_scripts', 'mlFloorball_scripts');
    add_action('wp_enqueue_scripts', 'mlFloorball_styles');
}

function mlFloorball_admin_init()
{
    add_action('admin_print_scripts', 'mlFloorball_scripts');
    add_action('admin_print_styles', 'mlFloorball_styles');
}

function mlFloorball_scripts()
{
    wp_enqueue_script(
        'all_js',
        sprintf('%s/mlFloorball.js', plugins_url('/mlFloorball')),
        array('jquery')
    );
}

function mlFloorball_styles()
{
    wp_enqueue_style(
        'all_css',
        sprintf('%s/mlFloorball.css', plugins_url('/mlFloorball'))
    );
}

function mlFloorball_admin_menu()
{
    add_menu_page(
        'mlFloorball',
        'mlFloorball',
        'manage_options',
        '/mlFloorball',
        'mlFloorball_options',
        'dashicons-mlFloorball'
    );
    add_submenu_page(
        '/mlFloorball',
        'Options',
        'Options',
        'manage_options',
        '/mlFloorball',
        'mlFloorball_options'
    );
    add_submenu_page(
        '/mlFloorball',
        'F.A.Q.',
        'F.A.Q.',
        'manage_options',
        '/mlFloorball/faq',
        'mlFloorball_faq'
    );
}

function mlFloorball_flashes()
{
    ?>
    <?php if (!empty($_SESSION['mlFloorball']['flashes'])) : ?>
        <?php foreach (
            $_SESSION['mlFloorball']['flashes'] AS $key => $value
        ) : ?>
            <div class="<?php echo $key; ?>">
                <p><strong><?php echo $value; ?></strong></p>
            </div>
        <?php endforeach; ?>
        <?php $_SESSION['mlFloorball']['flashes'] = array(); ?>
    <?php endif; ?>
    <?php
}

function mlFloorball_options()
{
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permissions to access this page.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        update_option(
            'mlFloorball_league_game_class',
            $_REQUEST['league_game_class'],
            true
        );
        update_option(
            'mlFloorball_season',
            $_REQUEST['season'],
            true
        );
        update_option(
            'mlFloorball_club_id',
            $_REQUEST['club_id'],
            true
        );
        update_option(
            'mlFloorball_team_id',
            $_REQUEST['team_id'],
            true
        );
        $_SESSION['mlFloorball']['flashes'] = array(
            'updated' => 'Your options were updated successfully.',
        );
        ?>
        <meta
            content="0;url=<?php echo admin_url(
                'admin.php?page=mlFloorball'
            ); ?>"
            http-equiv="refresh"
            >
        <?php
        die();
    }

    $league_game_class = get_option('mlFloorball_league_game_class');
    $season = get_option('mlFloorball_season');
    $club_id = get_option('mlFloorball_club_id');
    $team_id = get_option('mlFloorball_team_id');

    $leagues = mlFloorball_get_leagues();
    $seasons = mlFloorball_get_seasons();
    $clubs = mlFloorball_get_clubs();
    ?>
    <div class="mlFloorball">
        <h2>mlFloorball :: Options</h2>
        <?php mlFloorball_flashes(); ?>
        <form
            action="<?php echo admin_url(
                'admin.php?page=mlFloorball'
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
                        <label for="club_id">Default Club</label>
                    </td>
                    <td>
                        <select id="team_id" name="club_id">
                            <?php foreach ($clubs as $club) : ?>
                                <option
                                    <?php if ($club_id === $club['club_id']) : ?>
                                        selected="selected"
                                    <?php endif; ?>
                                    value="<?php echo $club['club_id']; ?>"
                                    >
                                    <?php echo $club['text'];?>
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
                            <option value="-1">All Teams</option>
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

function mlFloorball_faq()
{
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permissions to access this page.');
    }

    ?>
    <div class="mlFloorball">
        <h2>mlFloorball :: Frequently Asked Questions</h2>
        <div class="welcome-panel">
            <h2>How to obtain a <strong>shortcode?</strong></h2>
            <hr>
            <p>
                You can obtain a <strong>shortcode</strong> by embedding the
                following text in your page(s)/post(s) :
            </p>
            <pre>[mlFloorball]</pre>
        </div>
    </div>
    <?php
}

function mlFloorball_shortcode()
{
    $pane = $_REQUEST['pane']? $_REQUEST['pane']: '';
    if ($pane !== '1' and $pane !== '2' and $pane !== '3' and $pane !== '4' and $pane !== '5') {
        $pane = '1';
    }
    if ($pane === '1') {
        mlFloorball_shortcode_1(
            $_REQUEST['league']? $_REQUEST['league']: '',
            $_REQUEST['game_class']? $_REQUEST['game_class']: '',
            $_REQUEST['season']? $_REQUEST['season']: '',
            $_REQUEST['team_id']? $_REQUEST['team_id']: '',
            $_REQUEST['round']? $_REQUEST['round']: ''
        );
    }
    if ($pane === '2') {
        mlFloorball_shortcode_2(
            $_REQUEST['league']? $_REQUEST['league']: '',
            $_REQUEST['game_class']? $_REQUEST['game_class']: '',
            $_REQUEST['season']? $_REQUEST['season']: '',
            $_REQUEST['team_id']? $_REQUEST['team_id']: '',
            $_REQUEST['round']? $_REQUEST['round']: '',
            $_REQUEST['game_id']? $_REQUEST['game_id']: ''
        );
    }
    if ($pane === '3') {
        mlFloorball_shortcode_3(
            $_REQUEST['league']? $_REQUEST['league']: '',
            $_REQUEST['game_class']? $_REQUEST['game_class']: '',
            $_REQUEST['season']? $_REQUEST['season']: '',
            $_REQUEST['team_id']? $_REQUEST['team_id']: '',
            $_REQUEST['round']? $_REQUEST['round']: ''
        );
    }
    if ($pane === '4') {
        mlFloorball_shortcode_4(
            isset($_REQUEST['team_id'])? $_REQUEST['team_id']: null
        );
    }
    if ($pane === '5') {
        mlFloorball_shortcode_5(
            isset($_REQUEST['season'])? $_REQUEST['season']: date('Y'),
            isset($_REQUEST['page'])? $_REQUEST['page']: '1'
        );
    }
}

function mlFloorball_get_games_of_club($season, $page) {
    $item = $GLOBALS['mlFloorball']['pool']->getItem(
        sprintf('games/%s/%s/%s', get_option('mlFloorball_club_id'), $season, $page)
    );
    $games = $item->get();
    if ($item->isMiss()) {
        $url = sprintf(
            'https://api-v2.swissunihockey.ch/api/games?club_id=%s&season=%s&mode=club',
            get_option('mlFloorball_club_id'),
            $season
        );
        if ($page !== '1') {
            $url = sprintf('%s&page=%s', $url, $page);
        }
        $response = wp_remote_get($url);
        $games = array(
            'items' => array(),
            'slider' => array(),
        );
        if (isset($response['body'])) {
            $body = json_decode($response['body'], true);
            $rows = $body['data']['regions'][0]['rows'];
            foreach ($rows as $row) {
                $status = 'Not yet started';
                $now = strtotime('now');
                $timestamp = strtotime(implode(' ', $row['cells'][0]['text']));
                if ($timestamp > $now) {
                    $status = 'Not yet started';
                } else if (($now - $timestamp) < 7200) {
                    $status = 'Ongoing';
                } else {
                    $status = 'Finished';
                }
                $games['items'][] = array(
                    'game_id' => $row['link']['ids'][0],
                    'game_class' => $row['cells'][2]['link']['ids'][2],
                    'date' => $row['cells'][0]['text'][0],
                    'time' => $row['cells'][0]['text'][1],
                    'location' => implode(' - ', $row['cells'][1]['text']),
                    'league' => $row['cells'][2]['text'][0],
                    'league_id' => $row['cells'][2]['link']['ids'][1],
                    'home' => $row['cells'][3]['text'][0],
                    'away' => $row['cells'][4]['text'][0],
                    'score' => $row['cells'][5]['text'][0],
                    'status' => $status,
                );
            }
            $games['slider'] = isset($body['data']['slider'])? $body['data']['slider']: array();
        }
        $item->set($games);
        $item->expiresAfter(86400);
        $GLOBALS['mlFloorball']['pool']->save($item);
    }

    return $games;
}

function mlFloorball_display_next_three_games($team_id) {
    $item = $GLOBALS['mlFloorball']['pool']->getItem(
        sprintf('games/%s/next-three-games/%s/%s', get_option('mlFloorball_club_id'), date('Y'), $team_id)
    );
    $rows = $item->get();
    if ($item->isMiss()) {
        $response = array();
        $rows = array();
        if ($team_id !== '') {
            $response = wp_remote_get(sprintf(
                'https://api-v2.swissunihockey.ch/api/games?team_id=%s&season=%s&mode=team',
                $team_id,
                date('Y')
            ));
        } else {
            $response = wp_remote_get(sprintf(
                'https://api-v2.swissunihockey.ch/api/games?club_id=%s&season=%s&mode=club',
                get_option('mlFloorball_club_id'),
                date('Y')
            ));
        }
        if (isset($response['body'])) {
            $body = json_decode($response['body'], true);
            $rows = $body['data']['regions'][0]['rows'];
        }
        $item->set($rows);
        $item->expiresAfter(86400);
        $GLOBALS['mlFloorball']['pool']->save($item);
    } ?>
    <h1 class="text-center">Next 3 Games</h1>
    <?php
    $index = 0;
    foreach ($rows as $row):
        $game = mlFloorball_get_game($row['link']['ids'][0], false);
        if ($game['status'] === 'Not yet started'): ?>
            <?php
                if ($index > 2) {
                    break;
                }
                $index += 1;
            ?>
                <table class="table">
                    <tr>
                        <td class="text-center" colspan="3"><?php echo $game['league']; ?></td>
                    </tr>
                    <tr>
                    <tr></tr>
                        <td class="text-right">
                            <img
                                alt="<?php echo $game['home']['name']; ?>"
                                src="<?php echo $game['home']['logo']; ?>"
                                >
                            <br/>
                            <?php echo $game['home']['name']; ?>
                        </td>
                        <td class="text-center">-</td>
                        <td class="text-right">
                            <img
                                alt="<?php echo $game['away']['name']; ?>"
                                src="<?php echo $game['away']['logo']; ?>"
                                >
                            <br/>
                            <?php echo $game['away']['name']; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-center" colspan="3">
                            <?php echo $game['date']; ?> - <?php echo $game['time']; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-center" colspan="3"><?php echo $game['location']; ?></td>
                    </tr>
                </table>
        <?php endif ; ?>
    <?php endforeach ; ?>
    <?php
}

function mlFloorball_shortcode_1(
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
            get_option('mlFloorball_league_game_class');
        list($league, $game_class) = explode('-', $league_game_class);
    }

    if (!$season) {
        $season = $_REQUEST['season']?
            $_REQUEST['season']:
            get_option('mlFloorball_season');
    }

    if (!$team_id) {
        $team_id = $_REQUEST['team_id']?
            $_REQUEST['team_id']:
            get_option('mlFloorball_team_id');
    }

    $leagues = mlFloorball_get_leagues();
    $seasons = mlFloorball_get_seasons();
    $clubs = mlFloorball_get_clubs();

    $games = mlFloorball_get_games(
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
            '/mlFloorball'
        ); ?>/vendor/font-awesome/css/font-awesome.css"
        rel="stylesheet"
        >
    <link
        href="<?php echo plugins_url(
            '/mlFloorball'
        ); ?>/mlFloorball.css"
        rel="stylesheet"
        >
    <script
        src="<?php echo plugins_url(
            '/mlFloorball'
        ); ?>/mlFloorball.js"
        ></script>
    <div class="mlFloorball">
        <form
            action="<?php echo mlFloorball_get_url(
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
                            <option value="-1">All Teams</option>
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
                            href="<?php echo mlFloorball_get_url($url); ?>"
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
                    href="<?php echo mlFloorball_get_url(
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
                    href="<?php echo mlFloorball_get_url(
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
                    $url = mlFloorball_get_url(
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

function mlFloorball_shortcode_2(
    $league,
    $game_class,
    $season,
    $team_id,
    $round,
    $game_id
) {
    $game = mlFloorball_get_game($game_id, true);
    ?>
    <div class="mlFloorball">
        <p>
            <a
                href="<?php
                echo mlFloorball_get_url(
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

function mlFloorball_shortcode_3(
    $league,
    $game_class,
    $season,
    $team_id,
    $round
) {
    $table = mlFloorball_get_table($league, $game_class, $season);
    ?>
    <div class="mlFloorball">
        <p>
            <a
                href="<?php
                echo mlFloorball_get_url(
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

function mlFloorball_shortcode_4($team_id='') {

    $club_id = get_option('mlFloorball_club_id');

    ?>
    <link
        href="<?php echo plugins_url('/mlFloorball'); ?>/vendor/jquery-tabs/jquery-ui.css"
        rel="stylesheet"
        >
    <script src="<?php echo plugins_url('/mlFloorball'); ?>/vendor/jquery/dist/jquery.js"></script>
    <script src="<?php echo plugins_url('/mlFloorball'); ?>/vendor/jquery-tabs/jquery-ui.js"></script>
    <script type="text/javascript">
        jQuery(function() {
            jQuery('.mlFloorball').find('#tabs').tabs();
         });
    </script>
    <link
        href="<?php echo plugins_url(
            '/mlFloorball'
        ); ?>/vendor/font-awesome/css/font-awesome.css"
        rel="stylesheet"
        >
    <link
        href="<?php echo plugins_url(
            '/mlFloorball'
        ); ?>/mlFloorball.css"
        rel="stylesheet"
        >
    <script
        src="<?php echo plugins_url(
            '/mlFloorball'
        ); ?>/mlFloorball.js"
        ></script>
    <div class="mlFloorball">
        <div id="tabs">
            <ul>
                <li>
                    <a href="#All">
                        All
                    </a>
                </li>
                <?php foreach (mlFloorball_get_teams($club_id) as $team): ?>
                    <li>
                        <a href="#<?php echo $team['team_id']; ?>">
                            <?php echo $team['text']; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div id="All">
                <?php mlFloorball_display_next_three_games(''); ?>
            </div>
            <?php foreach (mlFloorball_get_teams($club_id) as $team): ?>
                <div id="<?php echo $team['team_id']; ?>">
                    <?php mlFloorball_display_next_three_games($team['team_id']); ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

function mlFloorball_shortcode_5($season, $page) {
    if (!$season) {
        $season = $_REQUEST['season']?
            $_REQUEST['season']:
            get_option('mlFloorball_season');
    }

    if (!$page) {
        $page = $_REQUEST['page']?
            $_REQUEST['page']:
            '1';
    }
    $seasons = mlFloorball_get_seasons();
    ?>
    <link
        href="<?php echo plugins_url(
            '/mlFloorball'
        ); ?>/vendor/font-awesome/css/font-awesome.css"
        rel="stylesheet"
        >
    <link
        href="<?php echo plugins_url(
            '/mlFloorball'
        ); ?>/mlFloorball.css"
        rel="stylesheet"
        >
    <script
        src="<?php echo plugins_url(
            '/mlFloorball'
        ); ?>/mlFloorball.js"
        ></script>
    <div class="mlFloorball">
        <form
            action="<?php echo mlFloorball_get_url(
                array(
                    'page' => '1',
                    'pane' => '5',
                    'season' => $season,
                )
            ); ?>"
            enctype="multipart/form-data"
            method="post"
            >
            <table class="table">
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
                    <td></td>
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
        <?php
        $games_slider = mlFloorball_get_games_of_club($season, $page);
        $games = $games_slider['items'];
        $slider = $games_slider['slider'];
        if (!empty($games)):
        ?>
            <p class="text-right">
                <?php if (isset($slider['prev'])) : ?>
                    <a
                        class="pull-left"
                        href="<?php echo mlFloorball_get_url(
                            array(
                                'page' => $slider['prev']['set_in_context']['page'],
                                'pane' => '5',
                                'season' => $season,
                            )
                        ); ?>"
                        >Previous</a>
                <?php else: ?>
                    <span class="pull-left line-through">Previous</span>
                <?php endif; ?>
                <?php if (isset($slider['next'])) : ?>
                    <a
                        href="<?php echo mlFloorball_get_url(
                            array(
                                'page' => $slider['next']['set_in_context']['page'],
                                'pane' => '5',
                                'season' => $season,
                            )
                        ); ?>"
                        >Next</a>
                <?php else: ?>
                    <span class="line-through">Next</span>
                <?php endif; ?>
            </p>
            <table class="table">
                <th class="text-center">Date/Time</th>
                <th class="text-center">Location</th>
                <th class="text-center">League</th>
                <th class="text-center">Home</th>
                <th class="text-center">Logo</th>
                <th class="text-center">Away</th>
                <th class="text-center">Logo</th>
                <th class="text-center">Score</th>
                <th class="text-center">Status</th>
                <?php foreach ($games as $game) : ?>
                    <?php
                    $game_details = mlFloorball_get_game($game['game_id'], false);
                    $url = mlFloorball_get_url(
                        array(
                            'pane' => '2',
                            'league' => $game['league_id'],
                            'game_class' => $game['game_class'],
                            'season' => $season,
                            'team_id' => '',
                            'round' => '',
                            'game_id' => $game['game_id'],
                        )
                    );
                    $away_url = mlFloorball_get_url(
                        array(
                            'pane' => '3',
                            'league' => $game['league_id'],
                            'game_class' => $game['game_class'],
                            'season' => $season,
                            'round' => '',
                            'team_id' => $game_details['away']['id'],
                        )
                    );
                    $home_url = mlFloorball_get_url(
                        array(
                            'pane' => '3',
                            'league' => $game['league_id'],
                            'game_class' => $game['game_class'],
                            'season' => $season,
                            'team_id' => '',
                            'round' => '',
                            'team_id' => $game_details['home']['id'],
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
                                <?php echo $game['league']; ?>
                            </a>
                        </td>
                        <td class="text-center">
                            <a href="<?php echo $home_url; ?>">
                                <?php echo $game['home']; ?>
                            </a>
                        </td>
                        <td class="text-center">
                            <a
                            href="<?php echo $home_url; ?>"
                            title="<?php echo $game['home']; ?>"
                            >
                                <img src="<?php echo $game_details['home']['logo']; ?>"/>
                            </a>
                        </td>
                        <td class="text-center">
                            <a
                                href="<?php echo $away_url; ?>"
                                title="<?php echo $game['away']; ?>"
                                >
                                <img src="<?php echo $game_details['away']['logo']; ?>"/>
                            </a>
                        </td>
                        <td class="text-center">
                            <a href="<?php echo $away_url; ?>">
                                <?php echo $game['away']; ?>
                            </a>
                        </td>
                        <td class="text-center">
                            <a href="<?php echo $url; ?>">
                                <?php echo $game['score']; ?>
                            </a>
                        </td>
                        <td class="text-center">
                            <a
                                class="icon" href="<?php echo $url; ?>"
                                >
                                <?php if ($game['status'] === 'Not yet started') : ?>
                                    <i class="fa fa-clock-o"></i>
                                <?php endif; ?>
                                <?php if ($game['status'] === 'Ongoing') : ?>
                                    <i class="fa fa-bullhorn"></i>
                                <?php endif; ?>
                                <?php if ($game['status'] === 'Finished') : ?>
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
        <?php
}

add_action('init', 'mlFloorball_init');

add_action('admin_init', 'mlFloorball_admin_init');
add_action('admin_menu', 'mlFloorball_admin_menu');

add_shortcode('mlFloorball', 'mlFloorball_shortcode');
