<?php

/**
 * This module documents the main interface with the OpenID consumer
 * libary. The only part of the library which has to be used and isn't
 * documented in full here is the store required to create an
 * OpenIDConsumer instance. More on the abstract store type and
 * concrete implementations of it that are provided in the
 * documentation for the constructor of the OpenIDConsumer class.
 *
 * OVERVIEW
 * ========
 *
 * The OpenID identity verification process most commonly uses the
 * following steps, as visible to the user of this library:
 *
 *   1. The user enters their OpenID into a field on the consumer's
 *   site, and hits a login button.
 *
 *   2. The consumer site checks that the entered URL describes an
 *   OpenID page by fetching it and looking for appropriate link tags
 *   in the head section.
 *
 *   3. The consumer site sends the browser a redirect to the identity
 *   server.  This is the authentication request as described in the
 *   OpenID specification.
 *
 *   4. The identity server's site sends the browser a redirect back
 *   to the consumer site.  This redirect contains the server's
 *   response to the authentication request.
 *
 * The most important part of the flow to note is the consumer's site
 * must handle two separate HTTP requests in order to perform the full
 * identity check.
 *
 *
 * LIBRARY DESIGN
 * ==============
 *
 * This consumer library is designed with that flow in mind.  The goal
 * is to make it as easy as possible to perform the above steps
 * securely.
 *
 * At a high level, there are two important parts in the consumer
 * library.  The first important part is this module, which contains
 * the interface to actually use this library.  The second is the
 * Net_OpenID_Interface class, which describes the interface to use if
 * you need to create a custom method for storing the state this
 * library needs to maintain between requests.
 *
 * In general, the second part is less important for users of the
 * library to know about, as several implementations are provided
 * which cover a wide variety of situations in which consumers may
 * use the library.
 *
 * This module contains a class, Net_OpenID_Consumer, with methods
 * corresponding to the actions necessary in each of steps 2, 3, and 4
 * described in the overview.  Use of this library should be as easy
 * as creating an Net_OpenID_Consumer instance and calling the methods
 * appropriate for the action the site wants to take.
 *
 *
 * STORES AND DUMB MODE
 * ====================
 *
 * OpenID is a protocol that works best when the consumer site is able
 * to store some state.  This is the normal mode of operation for the
 * protocol, and is sometimes referred to as smart mode.  There is
 * also a fallback mode, known as dumb mode, which is available when
 * the consumer site is not able to store state.  This mode should be
 * avoided when possible, as it leaves the implementation more
 * vulnerable to replay attacks.
 *
 * The mode the library works in for normal operation is determined by
 * the store that it is given.  The store is an abstraction that
 * handles the data that the consumer needs to manage between http
 * requests in order to operate efficiently and securely.
 *
 * Several store implementation are provided, and the interface is
 * fully documented so that custom stores can be used as well.  See
 * the documentation for the Net_OpenID_Consumer class for more
 * information on the interface for stores.  The concrete
 * implementations that are provided allow the consumer site to store
 * the necessary data in several different ways: in the filesystem, in
 * a MySQL database, or in an SQLite database.
 *
 * There is an additional concrete store provided that puts the system
 * in dumb mode.  This is not recommended, as it removes the library's
 * ability to stop replay attacks reliably.  It still uses time-based
 * checking to make replay attacks only possible within a small
 * window, but they remain possible within that window.  This store
 * should only be used if the consumer site has no way to retain data
 * between requests at all.
 *
 *
 * IMMEDIATE MODE
 * ==============
 *
 * In the flow described above, the user may need to confirm to the
 * identity server that it's ok to authorize his or her identity.  The
 * server may draw pages asking for information from the user before
 * it redirects the browser back to the consumer's site.  This is
 * generally transparent to the consumer site, so it is typically
 * ignored as an implementation detail.
 *
 * There can be times, however, where the consumer site wants to get a
 * response immediately.  When this is the case, the consumer can put
 * the library in immediate mode.  In immediate mode, there is an
 * extra response possible from the server, which is essentially the
 * server reporting that it doesn't have enough information to answer
 * the question yet.  In addition to saying that, the identity server
 * provides a URL to which the user can be sent to provide the needed
 * information and let the server finish handling the original
 * request.
 *
 *
 * USING THIS LIBRARY
 * ==================
 *
 * Integrating this library into an application is usually a
 * relatively straightforward process.  The process should basically
 * follow this plan:
 *
 * Add an OpenID login field somewhere on your site.  When an OpenID
 * is entered in that field and the form is submitted, it should make
 * a request to the your site which includes that OpenID URL.
 *
 * When your site receives that request, it should create an
 * Net_OpenID_Consumer instance, and call beginAuth on it.  If
 * beginAuth completes successfully, it will return an
 * Net_OpenID_AuthRequest instance.  Otherwise it will provide some
 * useful information for giving the user an error message.
 *
 * Now that you have the Net_OpenID_AuthRequest object, you need to
 * preserve the value in its $token field for lookup on the user's
 * next request from your site.  There are several approaches for
 * doing this which will work.  If your environment has any kind of
 * session-tracking system, storing the token in the session is a good
 * approach.  If it doesn't you can store the token in either a cookie
 * or in the return_to url provided in the next step.
 *
 * The next step is to call the constructRedirect method on the
 * Net_OpenID_Consumer object.  Pass it the Net_OpenID_AuthRequest
 * object returned by the previous call to beginAuth along with the
 * return_to and trust_root URLs.  The return_to URL is the URL that
 * the OpenID server will send the user back to after attempting to
 * verify his or her identity.  The trust_root is the URL (or URL
 * pattern) that identifies your web site to the user when he or she
 * is authorizing it.
 *
 * Next, send the user a redirect to the URL generated by
 * constructRedirect.
 *
 * That's the first half of the process.  The second half of the
 * process is done after the user's ID server sends the user a
 * redirect back to your site to complete their login.
 *
 * When that happens, the user will contact your site at the URL given
 * as the return_to URL to the constructRedirect call made above.  The
 * request will have several query parameters added to the URL by the
 * identity server as the information necessary to finish the request.
 *
 * When handling this request, the first thing to do is check the
 * 'openid.return_to' parameter.  If it doesn't match the URL that
 * the request was actually sent to (the URL the request was actually
 * sent to will contain the openid parameters in addition to any in
 * the return_to URL, but they should be identical other than that),
 * that is clearly suspicious, and the request shouldn't be allowed to
 * proceed.

 * Otherwise, the next step is to extract the token value set in the
 * first half of the OpenID login.  Create a Net_OpenID_Consumer
 * object, and call its completeAuth method with that token and a
 * dictionary of all the query arguments.  This call will return a
 * status code and some additional information describing the the
 * server's response.  See the documentation for completeAuth for a
 * full explanation of the possible responses.
 *
 * At this point, you have an identity URL that you know belongs to
 * the user who made that request.  Some sites will use that URL
 * directly as the user name.  Other sites will want to map that URL
 * to a username in the site's traditional namespace.  At this point,
 * you can take whichever action makes the most sense.
 *
 * PHP versions 4 and 5
 *
 * LICENSE: See the COPYING file included in this distribution.
 *
 * @package OpenID
 * @author JanRain, Inc. <openid@janrain.com>
 * @copyright 2005 Janrain, Inc.
 * @license http://www.gnu.org/copyleft/lesser.html LGPL
 */

require_once("Net/OpenID/CryptUtil.php");
require_once("Net/OpenID/KVForm.php");
require_once("Net/OpenID/OIDUtil.php");
require_once("Net/OpenID/Association.php");
require_once("Net/OpenID/DiffieHellman.php");
require_once("Net/OpenID/Consumer/Parse.php");
require_once("Net/OpenID/Consumer/Fetchers.php");

$Net_OpenID_SUCCESS = 'success';
$Net_OpenID_FAILURE = 'failure';
$Net_OpenID_SETUP_NEEDED = 'setup needed';
$Net_OpenID_HTTP_FAILURE = 'http failure';
$Net_OpenID_PARSE_ERROR = 'parse error';

// The following is a real hack and we'll have to figure out what is
// going on.  PHP bug?
$_Net_OpenID_NONCE_CHRS = $GLOBALS['_Net_OpenID_letters'] .
    $GLOBALS['_Net_OpenID_digits'];

$_Net_OpenID_TOKEN_LIFETIME = 60 * 5; // five minutes
$_Net_OpenID_NONCE_LEN = 8;

class Net_OpenID_Consumer {

    /**
     * NOTE: Be sure to pass $fetcher by reference if you pass one at
     * all:
     *
     * $consumer = new Net_OpenID_Consumer($store, &$fetcher, ...);
     */
    function Net_OpenID_Consumer(&$store, $fetcher = null, $immediate = false)
    {
        if ($store === null) {
            trigger_error("Must supply non-null store to create consumer",
                          E_USER_ERROR);
            return null;
        }

        $this->store =& $store;

        if ($fetcher === null) {
            $this->fetcher = Net_OpenID_getHTTPFetcher();
        } else {
            $this->fetcher =& $fetcher;
        }

        if ($immediate) {
            $this->mode = 'checkid_immediate';
        } else {
            $this->mode = 'checkid_setup';
        }

        $this->immediate = $immediate;
    }

    function beginAuth($user_url)
    {
        global $Net_OpenID_SUCCESS;

        list($status, $info) = $this->_findIdentityInfo($user_url);
        if ($status != $Net_OpenID_SUCCESS) {
            return array($status, $info);
        }

        list($consumer_id, $server_id, $server_url) = $info;
        return $this->_gotIdentityInfo($consumer_id, $server_id, $server_url);
    }

    function constructRedirect($auth_request, $return_to, $trust_root)
    {
        $assoc = $this->_getAssociation($auth_request->server_url,
                                        $replace = 1);
        // Because _getAssociation is asynchronous if the association is
        // not already in the store.

        if ($assoc === null) {
            trigger_error("Could not get association for redirection",
                          E_USER_WARNING);
            return null;
        }

        return $this->_constructRedirect($assoc, $auth_request,
                                         $return_to, $trust_root);
    }

    function fixResponse($arr)
    {
        // Depending on PHP settings, the query data received may have
        // been modified so that incoming "." values in the keys have
        // been replaced with underscores.  Look specifically for
        // "openid_" and replace it with "openid.".
        $result = array();

        foreach ($arr as $key => $value) {
            $new_key = str_replace("openid_", "openid.", $key);
            $result[$new_key] = $value;
        }

        return $result;
    }

    function completeAuth($token, $query)
    {
        global $Net_OpenID_SUCCESS, $Net_OpenID_FAILURE;

        $query = $this->fixResponse($query);

        $mode = Net_OpenID_array_get($query, 'openid.mode', '');

        if ($mode == 'cancel') {
            return array($Net_OpenID_SUCCESS, null);
        } else if ($mode == 'error') {

            $error = Net_OpenID_array_get($query, 'openid.error', null);

            if ($error !== null) {
                Net_OpenID_log($error);
            }
            return array($Net_OpenID_FAILURE, null);
        } else if ($mode == 'id_res') {
            return $this->_doIdRes($token, $query);
        } else {
            return array($Net_OpenID_FAILURE, null);
        }
    }

    function _gotIdentityInfo($consumer_id, $server_id, $server_url)
    {
        global $Net_OpenID_SUCCESS, $_Net_OpenID_NONCE_CHRS,
            $_Net_OpenID_NONCE_LEN;

        $nonce = Net_OpenID_CryptUtil::randomString($_Net_OpenID_NONCE_LEN,
                                                    $_Net_OpenID_NONCE_CHRS);

        $token = $this->_genToken($nonce, $consumer_id,
                                  $server_id, $server_url);
        return array($Net_OpenID_SUCCESS,
                     new Net_OpenID_AuthRequest($token, $server_id,
                                                $server_url, $nonce));
    }

    function _constructRedirect($assoc, $auth_req, $return_to, $trust_root)
    {
        $redir_args = array(
                            'openid.identity' => $auth_req->server_id,
                            'openid.return_to' => $return_to,
                            'openid.trust_root' => $trust_root,
                            'openid.mode' => $this->mode,
                            );

        if ($assoc !==  null) {
            $redir_args['openid.assoc_handle'] = $assoc->handle;
        }

        $this->store->storeNonce($auth_req->nonce);
        return strval(Net_OpenID_appendArgs($auth_req->server_url,
                                            $redir_args));
    }

    function _doIdRes($token, $query)
    {
        global $Net_OpenID_FAILURE, $Net_OpenID_SETUP_NEEDED,
            $Net_OpenID_SUCCESS;

        $ret = $this->_splitToken($token);
        if ($ret === null) {
            return array($Net_OpenID_FAILURE, null);
        }

        list($nonce, $consumer_id, $server_id, $server_url) = $ret;

        $return_to = Net_OpenID_array_get($query, 'openid.return_to', null);
        $server_id2 = Net_OpenID_array_get($query, 'openid.identity', null);
        $assoc_handle = Net_OpenID_array_get($query,
                                             'openid.assoc_handle', null);

        if (($return_to === null) ||
            ($server_id === null) ||
            ($assoc_handle === null)) {
            return array($Net_OpenID_FAILURE, $consumer_id);
        }

        if ($server_id != $server_id2) {
            return array($Net_OpenID_FAILURE, $consumer_id);
        }

        $user_setup_url = Net_OpenID_array_get($query,
                                               'openid.user_setup_url', null);

        if ($user_setup_url !== null) {
            return array($Net_OpenID_SETUP_NEEDED, $user_setup_url);
        }

        $assoc = $this->store->getAssociation($server_url);

        if (($assoc === null) ||
            ($assoc->handle != $assoc_handle) ||
            ($assoc->getExpiresIn() <= 0)) {
            // It's not an association we know about.  Dumb mode is
            // our only possible path for recovery.
            return array($this->_checkAuth($nonce, $query, $server_url),
                         $consumer_id);
        }

        // Check the signature
        $sig = Net_OpenID_array_get($query, 'openid.sig', null);
        $signed = Net_OpenID_array_get($query, 'openid.signed', null);
        if (($sig === null) ||
            ($signed === null)) {
            return array($Net_OpenID_FAILURE, $consumer_id);
        }

        $signed_list = explode(",", $signed);
        $v_sig = $assoc->signDict($signed_list, $query);

        if ($v_sig != $sig) {
            return array($Net_OpenID_FAILURE, $consumer_id);
        }

        if (!$this->store->useNonce($nonce)) {
            return array($Net_OpenID_FAILURE, $consumer_id);
        }

        return array($Net_OpenID_SUCCESS, $consumer_id);
    }

    function _checkAuth($nonce, $query, $server_url)
    {
        global $Net_OpenID_FAILURE, $Net_OpenID_SUCCESS;

        // XXX: send only those arguments that were signed?
        $signed = Net_OpenID_array_get($query, 'openid.signed', null);
        if ($signed === null) {
            return $Net_OpenID_FAILURE;
        }

        $whitelist = array('assoc_handle', 'sig',
                           'signed', 'invalidate_handle');

        $signed = array_merge(explode(",", $signed), $whitelist);

        $check_args = array();

        foreach ($query as $key => $value) {
            if (in_array(substr($key, 7), $signed)) {
                $check_args[$key] = $value;
            }
        }

        $check_args['openid.mode'] = 'check_authentication';
        $post_data = Net_OpenID_http_build_query($check_args);

        $ret = $this->fetcher->post($server_url, $post_data);
        if ($ret === null) {
            return $Net_OpenID_FAILURE;
        }

        $results = Net_OpenID_KVForm::kvToArray($ret[2]);
        $is_valid = Net_OpenID_array_get($results, 'is_valid', 'false');

        if ($is_valid == 'true') {
            $invalidate_handle = Net_OpenID_array_get($results,
                                                      'invalidate_handle',
                                                      null);
            if ($invalidate_handle !== null) {
                $this->store->removeAssociation($server_url,
                                                $invalidate_handle);
            }

            if (!$this->store->useNonce($nonce)) {
                return $Net_OpenID_FAILURE;
            }

            return $Net_OpenID_SUCCESS;
        }

        $error = Net_OpenID_array_get($results, 'error', null);
        if ($error !== null) {
            Net_OpenID_log(sprintf("Error message from server during " .
                                   "check_authentication: %s", error));
        }

        return $Net_OpenID_FAILURE;
    }

    function _getAssociation($server_url, $replace = false)
    {
        global $_Net_OpenID_TOKEN_LIFETIME;

        if ($this->store->isDumb()) {
            return null;
        }

        $assoc = $this->store->getAssociation($server_url);

        if (($assoc === null) ||
            ($replace && ($assoc->getExpiresIn() <
                          $_Net_OpenID_TOKEN_LIFETIME))) {
            $dh = new Net_OpenID_DiffieHellman();
            $body = $this->_createAssociateRequest($dh);
            $assoc = $this->_fetchAssociation($dh, $server_url, $body);
        }

        return $assoc;
    }

    function _genToken($nonce, $consumer_id, $server_id, $server_url)
    {
        $timestamp = strval(time());
        $elements = array($timestamp, $nonce,
                          $consumer_id, $server_id, $server_url);

        $joined = implode("\x00", $elements);
        $sig = Net_OpenID_CryptUtil::hmacSha1($this->store->getAuthKey(),
                                              $joined);

        return Net_OpenID_toBase64($sig . $joined);
    }

    function _splitToken($token)
    {
        global $_Net_OpenID_TOKEN_LIFETIME;

        $token = Net_OpenID_fromBase64($token);
        if (strlen($token) < 20) {
            return null;
        }

        $sig = substr($token, 0, 20);
        $joined = substr($token, 20);
        if (Net_OpenID_CryptUtil::hmacSha1(
              $this->store->getAuthKey(), $joined) != $sig) {
            return null;
        }

        $split = explode("\x00", $joined);
        if (count($split) != 5) {
            return null;
        }

        $ts = intval($split[0]);
        if ($ts == 0) {
            return null;
        }

        if ($ts + $_Net_OpenID_TOKEN_LIFETIME < time()) {
            return null;
        }

        return array_slice($split, 1);
    }

    function _findIdentityInfo($identity_url)
    {
        global $Net_OpenID_HTTP_FAILURE;

        $url = Net_OpenID_normalizeUrl($identity_url);
        $ret = $this->fetcher->get($url);
        if ($ret === null) {
            return array($Net_OpenID_HTTP_FAILURE, null);
        }

        list($http_code, $consumer_id, $data) = $ret;
        if ($http_code != 200) {
            return array($Net_OpenID_HTTP_FAILURE, $http_code);
        }

        // This method is split in two this way to allow for
        // asynchronous implementations of _findIdentityInfo.
        return $this->_parseIdentityInfo($data, $consumer_id);
    }

    function _parseIdentityInfo($data, $consumer_id)
    {
        global $Net_OpenID_PARSE_ERROR, $Net_OpenID_SUCCESS;

        $link_attrs = Net_OpenID_parseLinkAttrs($data);
        $server = Net_OpenID_findFirstHref($link_attrs, 'openid.server');
        $delegate = Net_OpenID_findFirstHref($link_attrs, 'openid.delegate');

        if ($server === null) {
            return array($Net_OpenID_PARSE_ERROR, null);
        }

        if ($delegate !== null) {
            $server_id = $delegate;
        } else {
            $server_id = $consumer_id;
        }

        $urls = array($consumer_id, $server_id, $server);

        $normalized = array();

        foreach ($urls as $url) {
            $normalized[] = Net_OpenID_normalizeUrl($url);
        }

        return array($Net_OpenID_SUCCESS, $normalized);
    }

    function _createAssociateRequest($dh, $args = null)
    {
        global $_Net_OpenID_DEFAULT_MOD, $_Net_OpenID_DEFAULT_GEN;

        if ($args === null) {
            $args = array();
        }

        $cpub = Net_OpenID_CryptUtil::longToBase64($dh->public);

        $args = array_merge($args, array(
                                         'openid.mode' =>  'associate',
                                         'openid.assoc_type' => 'HMAC-SHA1',
                                         'openid.session_type' => 'DH-SHA1',
                                         'openid.dh_consumer_public' => $cpub
                                         ));

        if (($dh->mod != $_Net_OpenID_DEFAULT_MOD) ||
            ($dh->gen != $_Net_OpenID_DEFAULT_GEN)) {
            $args = array_merge($args,
                     array(
                           'openid.dh_modulus' =>
                           Net_OpenID_CryptUtil::longToBase64($dh->modulus),
                           'openid.dh_gen' =>
                           Net_OpenID_CryptUtil::longToBase64($dh->generator)
                           ));
        }

        return Net_OpenID_http_build_query($args);
    }

    function _fetchAssociation($dh, $server_url, $body)
    {
        $ret = $this->fetcher->post($server_url, $body);
        if ($ret === null) {
            $fmt = 'Getting association: failed to fetch URL: %s';
            Net_OpenID_log(sprintf($fmt, $server_url));
            return null;
        }

        list($http_code, $url, $data) = $ret;
        $results = Net_OpenID_KVForm::kvToArray($data);
        if ($http_code == 400) {
            $server_error = Net_OpenID_array_get($results, 'error',
                                                 '<no message from server>');

            $fmt = 'Getting association: error returned from server %s: %s';
            Net_OpenID_log(sprintf($fmt, $server_url, $server_error));
            return null;
        } else if ($http_code != 200) {
            $fmt = 'Getting association: bad status code from server %s: %s';
            Net_OpenID_log(sprintf($fmt, $server_url, $http_code));
            return null;
        }

        $results = Net_OpenID_KVForm::kvToArray($data);

        return $this->_parseAssociation($results, $dh, $server_url);
    }

    function _parseAssociation($results, $dh, $server_url)
    {
        $required_keys = array('assoc_type', 'assoc_handle',
                               'dh_server_public', 'enc_mac_key');

        foreach ($required_keys as $key) {
            if (!array_key_exists($key, $results)) {
                Net_OpenID_log(sprintf("Getting association: missing key in ".
                                       "response from %s: %s",
                                       $server_url, $key),
                               E_USER_WARNING);
                return null;
            }
        }

        $assoc_type = $results['assoc_type'];
        if ($assoc_type != 'HMAC-SHA1') {
            $fmt = 'Unsupported assoc_type returned from server %s: %s';
            Net_OpenID_log(sprintf($fmt, $server_url, $assoc_type));
            return null;
        }

        $assoc_handle = $results['assoc_handle'];
        $expires_in = intval(Net_OpenID_array_get($results, 'expires_in', '0'));

        $session_type = Net_OpenID_array_get($results, 'session_type', null);
        if ($session_type === null) {
            $secret = Net_OpenID_fromBase64($results['mac_key']);
        } else {
            $fmt = 'Unsupported session_type returned from server %s: %s';
            if ($session_type != 'DH-SHA1') {
                Net_OpenID_log(sprintf($fmt, $server_url, $session_type));
                return null;
            }

            $spub = Net_OpenID_CryptUtil::base64ToLong(
                         $results['dh_server_public']);

            $enc_mac_key = Net_OpenID_CryptUtil::fromBase64(
                         $results['enc_mac_key']);

            $secret = $dh->xorSecret($spub, $enc_mac_key);
        }

        $assoc = Net_OpenID_Association::fromExpiresIn($expires_in,
                                                       $assoc_handle,
                                                       $secret,
                                                       $assoc_type);

        $this->store->storeAssociation($server_url, $assoc);
        return $assoc;
    }
}

class Net_OpenID_AuthRequest {
    function Net_OpenID_AuthRequest($token, $server_id, $server_url, $nonce)
    {
        $this->token = $token;
        $this->server_id = $server_id;
        $this->server_url = $server_url;
        $this->nonce = $nonce;
    }
}

?>