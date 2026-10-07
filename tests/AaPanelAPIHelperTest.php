<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../AaPanelAPIHelper.php';

class AaPanelAPIHelperTest extends TestCase
{
    public function testChangeWordPressPasswordSuccess()
    {
        // Giả lập thông tin kết nối aaPanel và site WordPress
        $panelUrl = 'http://fake-aapanel-url';
        $panelUser = 'admin';
        $panelPass = 'adminpass';
        $siteId = '1';
        $wpUser = 'wpadmin';
        $newPassword = 'newpass123';

        // Tạo mock AaPanelAPI (giả lập makeRequest trả về thành công)
        $mock = $this->getMockBuilder(AaPanelAPI::class)
            ->setConstructorArgs([$panelUrl, $panelUser, $panelPass])
            ->onlyMethods(['login', 'makeRequest'])
            ->getMock();
        $mock->method('login')->willReturn(true);
        $mock->method('makeRequest')->willReturn(json_encode(['status' => true]));

        $result = $mock->changeWordPressPassword($siteId, $wpUser, $newPassword);
        $this->assertTrue($result['success']);
    }

    public function testChangeWordPressPasswordFail()
    {
        $panelUrl = 'http://fake-aapanel-url';
        $panelUser = 'admin';
        $panelPass = 'adminpass';
        $siteId = '1';
        $wpUser = 'wpadmin';
        $newPassword = 'newpass123';

        $mock = $this->getMockBuilder(AaPanelAPI::class)
            ->setConstructorArgs([$panelUrl, $panelUser, $panelPass])
            ->onlyMethods(['login', 'makeRequest'])
            ->getMock();
        $mock->method('login')->willReturn(true);
        $mock->method('makeRequest')->willReturn(json_encode(['status' => false, 'msg' => 'Lỗi đổi mật khẩu']));

        $result = $mock->changeWordPressPassword($siteId, $wpUser, $newPassword);
        $this->assertFalse($result['success']);
        $this->assertEquals('Lỗi đổi mật khẩu', $result['error']);
    }
}
