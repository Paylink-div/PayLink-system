<?php
// rate_display.php - شاشة عرض أسعار الصرف العامة

$pageTitle = 'شاشة أسعار الصرف اليومية';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?php echo $pageTitle; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@600;800;900&display=swap');
        body {
            font-family: 'Cairo', sans-serif;
            margin: 0;
            background-color: #0d47a1; /* خلفية زرقاء داكنة */
            color: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100vh;
            overflow: hidden;
        }
        .header {
            width: 100%;
            background-color: #ffc107; /* شريط أصفر */
            color: #333;
            padding: 15px 0;
            text-align: center;
            font-size: 3em;
            font-weight: 900;
        }
        .main-content {
            flex-grow: 1;
            width: 90%;
            max-width: 1400px;
            padding: 20px;
        }
        #rates-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); /* لتقسيم الشاشة */
            gap: 20px;
            margin-top: 30px;
        }
        .rate-card {
            background-color: #1565c0; /* أزرق أغمق للبطاقات */
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
            transition: transform 0.3s ease;
        }
        .rate-card h3 {
            font-size: 3.5em;
            margin-bottom: 20px;
            color: #ffc107;
            border-bottom: 3px solid #ffc107;
            padding-bottom: 10px;
        }
        .rate-info {
            display: flex;
            justify-content: space-around;
            margin-top: 20px;
        }
        .rate-item {
            display: flex;
            flex-direction: column;
        }
        .rate-label {
            font-size: 1.8em;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 5px;
        }
        .rate-value {
            font-size: 4em;
            font-weight: 800;
            color: #ffffff;
        }
        .buy-rate .rate-value {
             color: #4CAF50; /* أخضر */
        }
        .sell-rate .rate-value {
             color: #F44336; /* أحمر */
        }
        .footer {
            width: 100%;
            padding: 10px 0;
            background-color: #0a3574;
            text-align: center;
            font-size: 1.5em;
        }
    </style>
</head>
<body>

<div class="header">
    📈 أسعار الصرف الرسمية - PAYLINK
</div>

<div class="main-content">
    <div id="rates-container">
        </div>
</div>

<div class="footer">
    آخر تحديث: <span id="last-update">--:--:--</span>
</div>

<script>
    const API_ENDPOINT = 'display_api.php';
    const CONTAINER = document.getElementById('rates-container');
    const LAST_UPDATE = document.getElementById('last-update');
    const UPDATE_INTERVAL = 10000; // التحديث كل 10 ثواني

    function fetchRates() {
        fetch(API_ENDPOINT)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.rates.length > 0) {
                    CONTAINER.innerHTML = ''; // تفريغ المحتوى
                    
                    data.rates.forEach(rate => {
                        const card = document.createElement('div');
                        card.className = 'rate-card';
                        
                        // محتوى البطاقة
                        card.innerHTML = `
                            <h3>${rate.code}</h3>
                            <div class="rate-info">
                                <div class="buy-rate rate-item">
                                    <span class="rate-label">شراء (من الزبون)</span>
                                    <span class="rate-value">${rate.buy}</span>
                                </div>
                                <div class="sell-rate rate-item">
                                    <span class="rate-label">بيع (للزبون)</span>
                                    <span class="rate-value">${rate.sell}</span>
                                </div>
                            </div>
                        `;
                        CONTAINER.appendChild(card);
                    });

                    // تحديث وقت آخر تحديث
                    const date = new Date(data.timestamp * 1000);
                    LAST_UPDATE.textContent = date.toLocaleTimeString('ar-LY', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

                } else {
                    CONTAINER.innerHTML = '<p style="font-size: 2em; text-align: center;">لا توجد أسعار صرف مُفعّلة للعرض حالياً.</p>';
                }
            })
            .catch(error => {
                CONTAINER.innerHTML = '<p style="font-size: 2em; text-align: center;">⚠ فشل الاتصال بالخادم. حاول تحديث الصفحة يدوياً.</p>';
                console.error('Error fetching rates:', error);
            });
    }

    // تشغيل التحديث عند تحميل الصفحة
    fetchRates(); 
    // جدول التحديث الدوري
    setInterval(fetchRates, UPDATE_INTERVAL); 
</script>

</body>
</html>