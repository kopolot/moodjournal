<?php

namespace App\Translation;


/** 
 * This class contains all translation keys for the user.
 */
final class UserTranslationKeys
{
    /** 
     * User keys
     */
    public const USER_REGISTRATION_SUCCESS = 'user.registration.success';
    public const USER_REGISTRATION_FAILED = 'user.registration.failed';

    public const USER_LOGIN_SUCCESS = 'user.login.success';
    public const USER_LOGIN_FAILED = 'user.login.failed';

    public const USER_NOT_FOUND = 'user.not_found';

    public const USER_VERIFY_ALREADY = 'user.verify.already';
    public const USER_VERIFY_SUCCESS = 'user.verify.success';
    public const USER_SESSION_NOT_FOUND = 'user.session.not_found';

    public const USER_RESET_PASSWORD_SUCCESS = 'user.reset_password.success';
    public const USER_RESET_TOKEN_INVALID = 'user.reset_password.token_invalid';
    public const USER_CHANGE_PASSWORD_SUCCESS = 'user.change_password.success';
    public const USER_CURRENT_PASSWORD_INVALID = 'user.change_password.current_invalid';

    public const USER_ACCOUNT_INACTIVE = 'user.account.inactive';

    public const USER_EMAIL_FORGOT_PASSWORD_SUBJECT = 'email.forget_password.subject';

    public const USER_DISABLE_SUCCESS = 'user.disable.success';

    public const USER_EDIT_SUCCESS = 'user.edit.success';
    public const USER_EDIT_LANGUAGE_ERROR = 'user.edit.language_error';

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

    public const USER_PREFERENCES_INVALID = 'user.preferences.invalid';

    /** 
     * Forgot password keys
     */
    public const USER_FORGOT_PASSWORD_SUCCESS = 'user.forgot_password.success';

    /**
     * User email keys
     */
    public const USER_EMAIL_REGISTRATION_SUBJECT = 'email.registration.subject';

    public const USER_EMAIL_ACCOUNT_DISABLED_SUBJECT = 'email.account_disabled.subject';
    public const USER_EMAIL_ACCOUNT_DELETED_SUBJECT = 'email.account_deleted.subject';
}
