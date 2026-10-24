# MirrorHub Public API v1

API عمومی برای ابزارهای بیرونی، داشبوردها و اسکریپت‌ها. مسیر نسخه‌دار زیر /api/v1/ قرار دارد و مسیرهای قدیمی api.php?action=... برای سازگاری حفظ شده‌اند.

## سند ماشین‌خوان

- OpenAPI 3.1 JSON: /api/v1/openapi.json
- نمونه‌های curl: examples/api-v1.sh
- آدرس نمونهٔ نصب بتا: https://mirrorhub.ir/beta/api/v1؛ برای نصب‌های دیگر، بخش پایه را بر اساس SITE_URL تغییر دهید.

## مسیرها

| متد | مسیر | کاربرد |
|---|---|---|
| GET | /api/v1/mirrors | فهرست و جست‌وجوی میرورها |
| GET | /api/v1/mirrors/{slug} | جزئیات میرور فعال |
| GET | /api/v1/categories | فهرست دسته‌بندی‌ها |
| GET | /api/v1/stats | آمار عمومی سایت |
| GET | /api/v1/status | وضعیت میرورهای ارائه‌دهنده |
| GET | /api/v1/status/{slug}/history | تاریخچهٔ بررسی HTTP و آپتایم |
| POST | /api/v1/contributions | پیشنهاد میرور برای بررسی مدیر |
| GET | /api/v1/openapi.json | سند OpenAPI |

پاسخ موفق دارای success=true، data و meta است. خطاها دارای success=false و error.code / error.message هستند. خروجی فهرست‌ها مشخصات page، per_page، total و pages را هم در meta دارند.

## فیلترهای /mirrors

- q: نام فارسی/انگلیسی، توضیحات و URL؛ حداکثر ۱۲۰ نویسه.
- category یا category_id: نامک یا شناسهٔ دسته‌بندی.
- protocol: یکی از https، http، ftp، rsync یا custom.
- http_status: یکی از up، slow، down یا unknown.
- repository_health: یکی از healthy، stale، invalid، error، unknown یا not_configured.
- page: از ۱ تا ۱۰۰۰۰؛ per_page: از ۱ تا ۱۰۰.

## نمونه‌های درخواست

    curl -fsS 'https://mirrorhub.ir/beta/api/v1/mirrors?q=debian&per_page=10'
    curl -fsS 'https://mirrorhub.ir/beta/api/v1/mirrors?protocol=https&http_status=up'
    curl -fsS 'https://mirrorhub.ir/beta/api/v1/categories?type=software'
    curl -fsS 'https://mirrorhub.ir/beta/api/v1/stats'
    curl -fsS 'https://mirrorhub.ir/beta/api/v1/status'
    curl -fsS 'https://mirrorhub.ir/beta/api/v1/mirrors/replace-with-a-real-slug'

## ثبت پیشنهاد از طریق API

این مسیر میرور را فعال نمی‌کند؛ فقط درخواست pending ایجاد می‌کند تا مدیر آن را بررسی کند. هر IP حداکثر ۸ تلاش در ساعت دارد. IP خام ذخیره نمی‌شود؛ محدودیت از HMAC کلیددار روی REMOTE_ADDR استفاده می‌کند. نصب‌های پشت reverse proxy باید proxy مورد اعتماد را در لایهٔ وب‌سرور تنظیم کنند.

    curl -fsS -X POST 'https://mirrorhub.ir/beta/api/v1/contributions' \
      -H 'Content-Type: application/json' \
      --data '{
        "name_fa": "نام میرور",
        "name_en": "Example Mirror",
        "urls": ["https://mirror.example.invalid/repository"],
        "protocols": ["https"],
        "description": "توضیح کوتاه",
        "phone": "+989121234567",
        "email": "you@example.com",
        "website": ""
      }'

نمونه از دادهٔ ساختگی استفاده می‌کند. اگر جدول‌های request_links یا request_protocols موجود نباشند، API خطا می‌دهد و پیشنهاد ناقص ذخیره نمی‌کند.

## حدود اعتبارسنجی سلامت

وضعیت HTTP و متادیتا جدا هستند. برچسب healthy یعنی مدخل متادیتای انتخاب‌شده با توجه به پروفایل تنظیم‌شده تازه/قابل‌دسترسی است؛ این نتیجه امضای GPG یا صحت تمام بسته‌ها را اثبات نمی‌کند.

## نکته‌های امنیتی

- API خواندنی عمومی است و به کلید مدیریتی نیاز ندارد.
- فهرست‌ها کش کوتاه و صفحه‌بندی محدود دارند.
- ارسال پیشنهاد دارای اعتبارسنجی URL، سقف ۱۰ لینک، تلهٔ ضدربات و محدودیت نرخ است.
- پیشنهاد تنها پس از بررسی مدیر منتشر می‌شود.
- جزئیات داخلی و خطاهای SQL در پاسخ عمومی ظاهر نمی‌شوند.
- مصرف‌کننده‌ها باید پاسخ‌ها را cache کنند؛ قبل از انتشار نهایی، محدودسازی لایهٔ وب و تنظیم reverse proxy نیز بررسی شود.
