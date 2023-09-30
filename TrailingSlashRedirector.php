<?php
namespace Axllent\TrailingSlash\Middleware;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;

/**
 * Ensure that a single trailing slash is always added to the URL.
 * URLs accessed via Ajax, contain $_GET vars, or that contain
 * an extension are ignored.
 */
class TrailingSlashRedirector implements HTTPMiddleware
{
    /**
     * URLS to ignore
     *
     * @var array
     */
    private static $ignore_paths = [
        'admin/',
        'dev/',
    ];

    /**
     * User-Agents to ignore
     *
     * @var array
     */
    private static $ignore_agents = [
        'silverstripe/staticpublishqueue',
    ];

    /**
     * Redirection status code
     *
     * @var int
     */
    private static $redirection_status_code = 301;

    /**
     * Process request
     *
     * @param HTTPRequest $request  HTTP request
     * @param callable    $delegate Delegate
     *
     * @return HTTPResponse
     */
    public function process(HTTPRequest $request, callable $delegate)
    {
        if (!self::$count) {
            $DateTime = new \DateTime();
            $time = $DateTime->format('D, d M Y H:i:s');
            echo " $time\n";
        }
        $count = ++self::$count;


        echo "============================== ATTEMPT $count ==============================\n";

        $ignore_paths = [];

        $ignore_config = Config::inst()
            ->get(TrailingSlashRedirector::class, 'ignore_paths');

        var_dump('$ignore_config', $ignore_config);

        foreach ($ignore_config as $iurl) {
            if ($quoted = preg_quote(ltrim($iurl, '/'), '/')) {
                array_push($ignore_paths, $quoted);
            }
        }
        var_dump('$ignore_paths', $ignore_paths);

        if ($request && ($request->isGET() || $request->isHEAD())) {
            // skip $ignore_paths and home (`/`)
            if ($request->getURL() == ''
                || preg_match(
                    '/^(' . implode('|', $ignore_paths) . ')/i',
                    $request->getURL()
                )
            ) {

                if (self::$count >= 10) {
                    die(__METHOD__.':'.__LINE__);
                } else {
                    echo "============================== IGNORED $count ==============================\n";
                }
                return $delegate($request);
            }

            $requested_url = Environment::getEnv('REQUEST_URI');

            $expected_url = rtrim(
                Director::baseURL() . $request->getURL(), '/'
            ) . '/';

            var_dump('$requested_url', $requested_url);
            var_dump('Director::baseURL() . $request->getURL()', Director::baseURL(), $request->getURL());
            var_dump('$expected_url', $expected_url);

            $urlPathInfo = pathinfo($requested_url);
            var_dump('$urlPathInfo', $urlPathInfo);

            $params = $request->getVars();
            var_dump('$params', $params);

            $ignore_agents = Config::inst()
                ->get(TrailingSlashRedirector::class, 'ignore_agents');
            var_dump('$ignore_agents', $ignore_agents);


            var_dump('!Director::is_ajax()', !Director::is_ajax());
            var_dump('!($ignore_agents && in_array(
                    $request->getHeader(\'User-Agent\'),
                    $ignore_agents
                ))', !($ignore_agents && in_array(
                    $request->getHeader('User-Agent'),
                    $ignore_agents
                )));
            var_dump('$request->getHeader(\'User-Agent\')', $request->getHeader('User-Agent'));
            var_dump('!isset($urlPathInfo[\'extension\'])', !isset($urlPathInfo['extension']));
            var_dump('empty($params)', empty($params));
            var_dump('!preg_match(
                    \'/^\' . preg_quote($expected_url, \'/\') . \'(?!\/)/i\',
                    $requested_url
                )', !preg_match(
                '/^' . preg_quote($expected_url, '/') . '(?!\/)/i',
                $requested_url
            ));

            if (!Director::is_ajax()
                && !($ignore_agents && in_array(
                    $request->getHeader('User-Agent'),
                    $ignore_agents
                ))
                && !isset($urlPathInfo['extension'])
                && empty($params)
                && !preg_match(
                    '/^' . preg_quote($expected_url, '/') . '(?!\/)/i',
                    $requested_url
                )
            ) {
                $params       = $request->getVars();
                $redirect_url = Controller::join_links($expected_url, '/');
                $response     = new HTTPResponse();
                $code         = Config::inst()->get(
                    TrailingSlashRedirector::class,
                    'redirection_status_code'
                );
                var_dump('$params', $params);
                var_dump('$redirect_url', $redirect_url);
                var_dump('$code', $code);

                if (self::$count >= 10) {
                    die(__METHOD__.':'.__LINE__);
                } else {
                    echo "============================== REDIRECT $count ==============================\n";
                }

                return $response->redirect($redirect_url, $code);
            }
        }

        $response = $delegate($request);

        var_dump('$response has body', !!$response->getBody());
        var_dump('$response getStatusCode', $response->getStatusCode());
        var_dump('$response getStatusDescription', $response->getStatusDescription());
        if (self::$count >= 10) {
            die(__METHOD__.':'.__LINE__);
        } else {
            echo "============================== NOT REDIRECT $count ==============================\n";
        }

        return $response;
    }

    private static $count = 0;
}
