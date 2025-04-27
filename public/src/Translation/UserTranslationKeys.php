<?php

namespace App\Translation;


/** 
 * This class contains all translation keys for the user.
 */
final class UserTranslationKeys{
    /** 
     * User keys
     */
    public const USER_REGISTRATION_SUCCESS = 'user.registration.success';
    public const USER_REGISTRATION_FAILED = 'user.registration.failed';

    public const USER_LOGIN_SUCCESS = 'user.login.success';
    public const USER_LOGIN_FAILED = 'user.login.failed';

    public const USER_NOT_FOUND = 'user.not.found';

    public const USER_VERIFY_ALREADY = 'user.verify.already';
    public const USER_VERIFY_SUCCESS = 'user.verify.success';
    public const USER_SESSION_NOT_FOUND = 'user.session.not_found';

    public const USER_RESET_PASSWORD_SUCCESS = 'user.reset_password.success';

    /** 
     * Validation keys
     */
    public const USER_FIRST_NAME_NOT_BLANK = 'user.first_name.not_blank';
    public const USER_FIRST_NAME_LENGTH = 'user.first_name.length';

    public const USER_EMAIL_NOT_BLANK = 'user.email.not_blank';
    public const USER_EMAIL_INVALID = 'user.email.invalid';

    public const USER_PASSWORD_NOT_BLANK = 'user.password.not_blank';
    public const USER_PASSWORD_LENGTH = 'user.password.length';

    public const USER_REPEAT_PASSWORD_NOT_BLANK = 'user.repeat_password.not_blank';
    public const USER_PASSWORD_NOT_MATCH = 'user.password.not_match';

    public const USER_PRIVACY_POLICY_NOT_ACCEPTED = 'user.privacy_policy.not_accepted';
}