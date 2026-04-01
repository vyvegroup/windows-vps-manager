# 🖥️ Windows VPS Manager

Tự động tạo Windows VPS miễn phí qua **GitHub Actions** và quản lý qua **PHP Web Panel**.

## ⚡ Tính năng

- 🖥️ Tạo Windows VPS (Windows Server 2022) chỉ bằng 1 click
- 🔐 Mã hóa AES-256-CBC — token KHÔNG bao giờ lưu plain text
- 📱 Mobile-first — quản lý VPS từ điện thoại
- 🌐 Ngrok RDP Tunnel — kết nối Remote Desktop từ xa
- 🔄 Tự động refresh trạng thái VPS
- 🛑 Dừng VPS bất cứ lúc nào

## 📋 Yêu cầu

- Hosting PHP 8.3+ (có cURL enabled)
- GitHub Personal Access Token (có quyền `repo` và `workflow`)
- (Tùy chọn) Ngrok Auth Token cho RDP tunnel

## 🚀 Cài đặt

1. Clone repository:
```bash
git clone https://github.com/vyvegroup/windows-vps-manager.git
cd windows-vps-manager
```

2. Upload tất cả file lên hosting PHP của bạn

3. Truy cập `https://yourdomain.com/index.php` để bắt đầu cài đặt

4. Nhập GitHub Token và Repository name → Token sẽ được **mã hóa AES-256-CBC** trước khi lưu

## 🔒 Bảo mật

- Token được mã hóa bằng AES-256-CBC với key do người dùng tạo
- File `config.php` chứa dữ liệu mã hóa (đã gitignored)
- Session bảo mật với HttpOnly + SameSite=Strict
- KHÔNG có token plain text trong source code

## 🏗️ Cấu trúc file

```
├── .github/workflows/
│   ├── provision-vps.yml    # Workflow tạo VPS
│   ├── stop-vps.yml         # Workflow dừng VPS
│   └── vps-status.yml       # Workflow kiểm tra trạng thái
├── index.php                # Entry point (không chứa token)
├── helpers.php              # Helper functions (mã hóa, GitHub API)
├── styles.php               # CSS
├── pages.php                # Page renderers
├── scripts.js               # JavaScript
├── config.example.php       # Config mẫu
├── .gitignore               # Bỏ qua file config
└── README.md
```

## ⚙️ GitHub Actions Workflow

### Tạo VPS (`provision-vps.yml`)
- Dùng `windows-latest` runner
- Tự động bật RDP (port 3389)
- Cài đặt Chrome, Notepad++, Python, Node.js...
- Hỗ trợ Ngrok tunnel cho RDP từ xa
- Tối đa 6 giờ (360 phút)

### Dừng VPS (`stop-vps.yml`)
- Cancel workflow đang chạy

### Kiểm tra (`vps-status.yml`)
- Liệt kê VPS đang chạy và đã hoàn thành

## 📱 Screenshot Flow

1. **Setup**: Nhập token → mã hóa → lưu config
2. **Dashboard**: Xem tất cả VPS (refresh tự động 30s)
3. **Tạo VPS**: Nhập tên, mật khẩu RDP, thời gian → Click tạo
4. **Kết nối RDP**: Dùng Ngrok URL hoặc xem logs GitHub

## ⚠️ Lưu ý

- GitHub Actions có giới hạn 6 giờ cho workflow
- Runner có thể bị reboot bất ngờ, hãy lưu dữ liệu quan trọng
- Mỗi GitHub account có giới hạn concurrent runners
- IP của runner thay đổi mỗi lần tạo mới

## 📄 License

MIT
