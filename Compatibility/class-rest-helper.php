<?php
namespace InterLinkGenius\Compatibility;

defined( 'ABSPATH' ) || exit;

/**
 * Rest_Helper stub - shim để tương thích khi Rank Math không hoạt động.
 * Cung cấp các phương thức tiện ích cho REST API.
 */
class Rest_Helper {

	/**
	 * Kiểm tra quyền admin hiện tại.
	 *
	 * @return bool
	 */
	public static function is_admin() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Lấy giá trị param từ request, với sanitize.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @param string           $key     Tên param.
	 * @param mixed            $default Giá trị mặc định.
	 * @return mixed
	 */
	public static function get_param( $request, $key, $default = null ) {
		$value = $request->get_param( $key );
		return ( null !== $value ) ? $value : $default;
	}

	/**
	 * Tạo response lỗi chuẩn.
	 *
	 * @param string $code    Mã lỗi.
	 * @param string $message Thông báo lỗi.
	 * @param int    $status  HTTP status code.
	 * @return \WP_Error
	 */
	public static function error( $code, $message, $status = 400 ) {
		return new \WP_Error( $code, $message, [ 'status' => $status ] );
	}

	/**
	 * Kiểm tra xem request có nonce hợp lệ không.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @param string           $action  Nonce action.
	 * @return bool
	 */
	public static function verify_nonce( $request, $action = 'wp_rest' ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( empty( $nonce ) ) {
			return false;
		}
		return (bool) wp_verify_nonce( $nonce, $action );
	}
}
