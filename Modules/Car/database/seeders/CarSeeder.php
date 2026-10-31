<?php

namespace Modules\Car\Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Car\Models\Car;
use Modules\Car\Models\CarCompany;
use Symfony\Component\Mime\MimeTypes;

class CarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @throws ConnectionException
     * @throws \Exception
     */
    public function run(): void
    {
        $carData = [
            1 => [ // Toyota
                'company_slug' => 'toyota',
                'company_name' => 'تویوتا',
                'cars' => [
                    ['slug' => 'camry', 'name' => 'کمری'],
                    ['slug' => 'corolla', 'name' => 'کرولا'],
                    ['slug' => 'rav4', 'name' => 'راوی ۴'],
                    ['slug' => 'land-cruiser', 'name' => 'لندکروزر'],
                    ['slug' => 'yaris', 'name' => 'یاریس'],
                    ['slug' => 'hilux', 'name' => 'هایلوکس'],
                ],
            ],
            2 => [ // Lexus
                'company_slug' => 'lexus',
                'company_name' => 'لکسوس',
                'cars' => [
                    ['slug' => 'es', 'name' => 'ای‌اس'],
                    ['slug' => 'rx', 'name' => 'آر‌ایکس'],
                    ['slug' => 'nx', 'name' => 'ان‌ایکس'],
                    ['slug' => 'lx', 'name' => 'ال‌ایکس'],
                    ['slug' => 'gx', 'name' => 'جی‌ایکس'],
                    ['slug' => 'ux', 'name' => 'یو‌ایکس'],
                ],
            ],
            3 => [ // Daihatsu
                'company_slug' => 'daihatsu',
                'company_name' => 'دایهاتسو',
                'cars' => [
                    ['slug' => 'terios', 'name' => 'تریوس'],
                    ['slug' => 'charade', 'name' => 'شاراد'],
                    ['slug' => 'gran-max', 'name' => 'گرن مکس'],
                    ['slug' => 'move', 'name' => 'موو'],
                ],
            ],
            4 => [ // Nissan
                'company_slug' => 'nissan',
                'company_name' => 'نیسان',
                'cars' => [
                    ['slug' => 'altima', 'name' => 'آلتیمیا'],
                    ['slug' => 'maxima', 'name' => 'ماکسیما'],
                    ['slug' => 'pathfinder', 'name' => 'پثفایندر'],
                    ['slug' => 'qashqai', 'name' => 'کشکای'],
                    ['slug' => 'x-trail', 'name' => 'ایکس‌تریل'],
                    ['slug' => 'gt-r', 'name' => 'جی‌تی‌آر'],
                ],
            ],
            5 => [ // Infiniti
                'company_slug' => 'infiniti',
                'company_name' => 'اینفینیتی',
                'cars' => [
                    ['slug' => 'q50', 'name' => 'کیو۵۰'],
                    ['slug' => 'qx50', 'name' => 'کیوایکس۵۰'],
                    ['slug' => 'qx60', 'name' => 'کیوایکس۶۰'],
                    ['slug' => 'qx80', 'name' => 'کیوایکس۸۰'],
                ],
            ],
            6 => [ // Honda
                'company_slug' => 'honda',
                'company_name' => 'هوندا',
                'cars' => [
                    ['slug' => 'accord', 'name' => 'آکورد'],
                    ['slug' => 'civic', 'name' => 'سیویک'],
                    ['slug' => 'cr-v', 'name' => 'سی‌آر‌وی'],
                    ['slug' => 'pilot', 'name' => 'پایلوت'],
                    ['slug' => 'hr-v', 'name' => 'اچ‌آر‌وی'],
                    ['slug' => 'fit', 'name' => 'فیت'],
                ],
            ],
            7 => [ // Acura
                'company_slug' => 'acura',
                'company_name' => 'آکورا',
                'cars' => [
                    ['slug' => 'tlx', 'name' => 'تی‌ال‌ایکس'],
                    ['slug' => 'mdx', 'name' => 'ام‌دی‌ایکس'],
                    ['slug' => 'rdx', 'name' => 'آر‌دی‌ایکس'],
                ],
            ],
            8 => [ // Mazda
                'company_slug' => 'mazda',
                'company_name' => 'مزدا',
                'cars' => [
                    ['slug' => 'mazda3', 'name' => 'مزدا ۳'],
                    ['slug' => 'mazda6', 'name' => 'مزدا ۶'],
                    ['slug' => 'cx-5', 'name' => 'سی‌ایکس ۵'],
                    ['slug' => 'cx-9', 'name' => 'سی‌ایکس ۹'],
                    ['slug' => 'mx-5', 'name' => 'ام‌ایکس ۵'],
                ],
            ],
            9 => [ // Mitsubishi
                'company_slug' => 'mitsubishi',
                'company_name' => 'میتسوبیشی',
                'cars' => [
                    ['slug' => 'outlander', 'name' => 'آتلاندر'],
                    ['slug' => 'pajero', 'name' => 'پاجرو'],
                    ['slug' => 'lancer', 'name' => 'لنسر'],
                    ['slug' => 'asx', 'name' => 'ای‌اس‌ایکس'],
                ],
            ],
            10 => [ // Subaru
                'company_slug' => 'subaru',
                'company_name' => 'سوبارو',
                'cars' => [
                    ['slug' => 'impreza', 'name' => 'ایمپرزا'],
                    ['slug' => 'forester', 'name' => 'فورستر'],
                    ['slug' => 'outback', 'name' => 'اوت‌بک'],
                    ['slug' => 'legacy', 'name' => 'لگاسی'],
                ],
            ],
            11 => [ // Suzuki
                'company_slug' => 'suzuki',
                'company_name' => 'سوزوکی',
                'cars' => [
                    ['slug' => 'swift', 'name' => 'سوئیفت'],
                    ['slug' => 'vitara', 'name' => 'ویتارا'],
                    ['slug' => 'grand-vitara', 'name' => 'گرند ویتارا'],
                    ['slug' => 'sx4', 'name' => 'اس‌ایکس۴'],
                    ['slug' => 'jimny', 'name' => 'جیمنی'],
                ],
            ],
            12 => [ // Hyundai
                'company_slug' => 'hyundai',
                'company_name' => 'هیوندای',
                'cars' => [
                    ['slug' => 'sonata', 'name' => 'سوناتا'],
                    ['slug' => 'elantra', 'name' => 'الانترا'],
                    ['slug' => 'tucson', 'name' => 'توسان'],
                    ['slug' => 'santa-fe', 'name' => 'سانتافه'],
                    ['slug' => 'accent', 'name' => 'اکسنت'],
                    ['slug' => 'kona', 'name' => 'کونا'],
                ],
            ],
            13 => [ // Kia
                'company_slug' => 'kia',
                'company_name' => 'کیا',
                'cars' => [
                    ['slug' => 'optima', 'name' => 'اپتیما'],
                    ['slug' => 'sportage', 'name' => 'اسپورتیج'],
                    ['slug' => 'carnival', 'name' => 'کارنیوال'],
                    ['slug' => 'rio', 'name' => 'ریو'],
                    ['slug' => 'sorento', 'name' => 'سورنتو'],
                ],
            ],
            14 => [ // Genesis
                'company_slug' => 'genesis',
                'company_name' => 'جنسیس',
                'cars' => [
                    ['slug' => 'g70', 'name' => 'جی۷۰'],
                    ['slug' => 'g80', 'name' => 'جی۸۰'],
                    ['slug' => 'g90', 'name' => 'جی۹۰'],
                    ['slug' => 'gv70', 'name' => 'جی‌وی۷۰'],
                ],
            ],
            15 => [ // SsangYong
                'company_slug' => 'ssangyong',
                'company_name' => 'سانگ‌یانگ',
                'cars' => [
                    ['slug' => 'tivoli', 'name' => 'تیوولی'],
                    ['slug' => 'korando', 'name' => 'کراندو'],
                    ['slug' => 'rexton', 'name' => 'رکستون'],
                ],
            ],
            16 => [ // Daewoo
                'company_slug' => 'daewoo',
                'company_name' => 'دوو',
                'cars' => [
                    ['slug' => 'lanos', 'name' => 'لانوس'],
                    ['slug' => 'nubira', 'name' => 'نوبیرا'],
                    ['slug' => 'matiz', 'name' => 'ماتیز'],
                    ['slug' => 'kalos', 'name' => 'کالوس'],
                ],
            ],
            17 => [ // Volkswagen
                'company_slug' => 'volkswagen',
                'company_name' => 'فولکس‌واگن',
                'cars' => [
                    ['slug' => 'golf', 'name' => 'گلف'],
                    ['slug' => 'passat', 'name' => 'پاسات'],
                    ['slug' => 'tiguan', 'name' => 'تیگوان'],
                    ['slug' => 'polo', 'name' => 'پولو'],
                ],
            ],
            18 => [ // Audi
                'company_slug' => 'audi',
                'company_name' => 'آئودی',
                'cars' => [
                    ['slug' => 'a3', 'name' => 'آ۳'],
                    ['slug' => 'a4', 'name' => 'آ۴'],
                    ['slug' => 'a6', 'name' => 'آ۶'],
                    ['slug' => 'q5', 'name' => 'کیو۵'],
                    ['slug' => 'q7', 'name' => 'کیو۷'],
                    ['slug' => 'tt', 'name' => 'تی‌تی'],
                ],
            ],
            19 => [ // Porsche
                'company_slug' => 'porsche',
                'company_name' => 'پورشه',
                'cars' => [
                    ['slug' => '911', 'name' => '۹۱۱'],
                    ['slug' => 'cayenne', 'name' => 'کاین'],
                    ['slug' => 'macan', 'name' => 'ماکان'],
                    ['slug' => 'panamera', 'name' => 'پانامرا'],
                ],
            ],
            20 => [ // BMW
                'company_slug' => 'bmw',
                'company_name' => 'بی‌ام‌و',
                'cars' => [
                    ['slug' => '3-series', 'name' => 'سری ۳'],
                    ['slug' => '5-series', 'name' => 'سری ۵'],
                    ['slug' => '7-series', 'name' => 'سری ۷'],
                    ['slug' => 'x3', 'name' => 'ایکس۳'],
                    ['slug' => 'x5', 'name' => 'ایکس۵'],
                    ['slug' => 'x6', 'name' => 'ایکس۶'],
                ],
            ],
            21 => [ // Mini
                'company_slug' => 'mini',
                'company_name' => 'مینی',
                'cars' => [
                    ['slug' => 'cooper', 'name' => 'کوپر'],
                    ['slug' => 'clubman', 'name' => 'کلابمن'],
                    ['slug' => 'countryman', 'name' => 'کانتری‌من'],
                ],
            ],
            22 => [ // Mercedes-Benz
                'company_slug' => 'mercedesbenz',
                'company_name' => 'مرسدس بنز',
                'cars' => [
                    ['slug' => 'c-class', 'name' => 'کلاس سی'],
                    ['slug' => 'e-class', 'name' => 'کلاس ای'],
                    ['slug' => 's-class', 'name' => 'کلاس اس'],
                    ['slug' => 'glc', 'name' => 'جی‌ال‌سی'],
                    ['slug' => 'gle', 'name' => 'جی‌ال‌ای'],
                    ['slug' => 'g-class', 'name' => 'کلاس جی'],
                ],
            ],
            23 => [ // Smart
                'company_slug' => 'smart',
                'company_name' => 'اسمارت',
                'cars' => [
                    ['slug' => 'fortwo', 'name' => 'فورتو'],
                ],
            ],
            24 => [ // Opel
                'company_slug' => 'opel',
                'company_name' => 'اوپل',
                'cars' => [
                    ['slug' => 'astra', 'name' => 'آسترا'],
                    ['slug' => 'corsa', 'name' => 'کورسا'],
                    ['slug' => 'insignia', 'name' => 'اینسینیا'],
                    ['slug' => 'mokka', 'name' => 'موکا'],
                ],
            ],
            25 => [ // Ford
                'company_slug' => 'ford',
                'company_name' => 'فورد',
                'cars' => [
                    ['slug' => 'focus', 'name' => 'فوکوس'],
                    ['slug' => 'fiesta', 'name' => 'فیستا'],
                    ['slug' => 'mustang', 'name' => 'موستانگ'],
                    ['slug' => 'explorer', 'name' => 'اکسپلورر'],
                    ['slug' => 'ranger', 'name' => 'رنجر'],
                ],
            ],
            26 => [ // Chevrolet
                'company_slug' => 'chevrolet',
                'company_name' => 'شورولت',
                'cars' => [
                    ['slug' => 'camaro', 'name' => 'کامارو'],
                    ['slug' => 'corvette', 'name' => 'کوروت'],
                    ['slug' => 'malibu', 'name' => 'مالیبو'],
                    ['slug' => 'tahoe', 'name' => 'تاهو'],
                    ['slug' => 'silverado', 'name' => 'سیلورادو'],
                ],
            ],
            27 => [ // Cadillac
                'company_slug' => 'cadillac',
                'company_name' => 'کادیلاک',
                'cars' => [
                    ['slug' => 'ct5', 'name' => 'سی‌تی۵'],
                    ['slug' => 'escalade', 'name' => 'اسکالید'],
                ],
            ],
            28 => [ // Buick
                'company_slug' => 'buick',
                'company_name' => 'بیوک',
                'cars' => [
                    ['slug' => 'envision', 'name' => 'انویژن'],
                    ['slug' => 'enclave', 'name' => 'انکلیو'],
                ],
            ],
            29 => [ // GMC
                'company_slug' => 'gmc',
                'company_name' => 'جی‌ام‌سی',
                'cars' => [
                    ['slug' => 'sierra', 'name' => 'سیرا'],
                    ['slug' => 'acadia', 'name' => 'آکادیا'],
                    ['slug' => 'yukon', 'name' => 'یوکان'],
                ],
            ],
            30 => [ // Dodge
                'company_slug' => 'dodge',
                'company_name' => 'دوج',
                'cars' => [
                    ['slug' => 'challenger', 'name' => 'چلنجر'],
                    ['slug' => 'charger', 'name' => 'چارجر'],
                    ['slug' => 'durango', 'name' => 'دورانگو'],
                ],
            ],
            31 => [ // Jeep
                'company_slug' => 'jeep',
                'company_name' => 'جیپ',
                'cars' => [
                    ['slug' => 'wrangler', 'name' => 'رنگلر'],
                    ['slug' => 'grand-cherokee', 'name' => 'گرند چروکی'],
                    ['slug' => 'cherokee', 'name' => 'چروکی'],
                    ['slug' => 'compass', 'name' => 'کامپس'],
                    ['slug' => 'liberty', 'name' => 'لیبرتی'],
                ],
            ],
            32 => [ // Tesla
                'company_slug' => 'tesla',
                'company_name' => 'تسلا',
                'cars' => [
                    ['slug' => 'model-s', 'name' => 'مدل اس'],
                    ['slug' => 'model-3', 'name' => 'مدل ۳'],
                    ['slug' => 'model-x', 'name' => 'مدل ایکس'],
                    ['slug' => 'model-y', 'name' => 'مدل وای'],
                ],
            ],
            33 => [ // Peugeot
                'company_slug' => 'peugeot',
                'company_name' => 'پژو',
                'cars' => [
                    ['slug' => '206', 'name' => '۲۰۶'],
                    ['slug' => '207', 'name' => '۲۰۷'],
                    ['slug' => '405', 'name' => '۴۰۵'],
                    ['slug' => 'pars', 'name' => 'پارس'],
                    ['slug' => '508', 'name' => '۵۰۸'],
                    ['slug' => '2008', 'name' => '۲۰۰۸'],
                ],
            ],
            34 => [ // Citroen
                'company_slug' => 'citroen',
                'company_name' => 'سیتروئن',
                'cars' => [
                    ['slug' => 'c3', 'name' => 'سی۳'],
                    ['slug' => 'c4', 'name' => 'سی۴'],
                    ['slug' => 'c5', 'name' => 'سی۵'],
                    ['slug' => 'ds3', 'name' => 'دی‌اس۳'],
                ],
            ],
            35 => [ // Renault
                'company_slug' => 'renault',
                'company_name' => 'رنو',
                'cars' => [
                    ['slug' => 'tondar-90', 'name' => 'تندر ۹۰'],
                    ['slug' => 'sandero', 'name' => 'ساندرو'],
                    ['slug' => 'duster', 'name' => 'داستر'],
                    ['slug' => 'megane', 'name' => 'مگان'],
                    ['slug' => 'koleos', 'name' => 'کولیوس'],
                ],
            ],
            36 => [ // Dacia
                'company_slug' => 'dacia',
                'company_name' => 'داسیا',
                'cars' => [
                    ['slug' => 'logan', 'name' => 'لوگان'],
                    ['slug' => 'sandero', 'name' => 'ساندرو'],
                    ['slug' => 'duster', 'name' => 'داستر'],
                ],
            ],
            37 => [ // Bugatti
                'company_slug' => 'bugatti',
                'company_name' => 'بوگاتی',
                'cars' => [
                    ['slug' => 'veyron', 'name' => 'ویرون'],
                    ['slug' => 'chiron', 'name' => 'شیرون'],
                ],
            ],
            38 => [ // Alpine
                'company_slug' => 'alpine',
                'company_name' => 'آلپاین',
                'cars' => [
                    ['slug' => 'a110', 'name' => 'آ۱۱۰'],
                ],
            ],
            39 => [ // Fiat
                'company_slug' => 'fiat',
                'company_name' => 'فیات',
                'cars' => [
                    ['slug' => 'palio', 'name' => 'پالیو'],
                    ['slug' => 'uno', 'name' => 'اونو'],
                    ['slug' => '500', 'name' => '۵۰۰'],
                    ['slug' => 'punto', 'name' => 'پونتو'],
                ],
            ],
            40 => [ // Alfa Romeo
                'company_slug' => 'alfa-romeo',
                'company_name' => 'آلفارومئو',
                'cars' => [
                    ['slug' => 'giulia', 'name' => 'جولیا'],
                    ['slug' => 'stelvio', 'name' => 'استلویو'],
                ],
            ],
            41 => [ // Lancia
                'company_slug' => 'lancia',
                'company_name' => 'لانچیا',
                'cars' => [
                    ['slug' => 'delta', 'name' => 'دلتا'],
                    ['slug' => 'thesis', 'name' => 'تز'],
                ],
            ],
            42 => [ // Ferrari
                'company_slug' => 'ferrari',
                'company_name' => 'فراری',
                'cars' => [
                    ['slug' => '458-italia', 'name' => '۴۵۸ ایتالیا'],
                    ['slug' => '488', 'name' => '۴۸۸'],
                    ['slug' => 'f8-tributo', 'name' => 'اف۸ تریبیوتو'],
                    ['slug' => 'sf90', 'name' => 'اس‌اف۹۰'],
                    ['slug' => 'roma', 'name' => 'روما'],
                ],
            ],
            43 => [ // Lamborghini
                'company_slug' => 'lamborghini',
                'company_name' => 'لامبورگینی',
                'cars' => [
                    ['slug' => 'aventador', 'name' => 'آونتادور'],
                    ['slug' => 'huracan', 'name' => 'هوراکان'],
                    ['slug' => 'urus', 'name' => 'اوروس'],
                    ['slug' => 'revuelto', 'name' => 'روولتو'],
                ],
            ],
            44 => [ // Maserati
                'company_slug' => 'maserati',
                'company_name' => 'مازراتی',
                'cars' => [
                    ['slug' => 'ghibli', 'name' => 'گیبلی'],
                    ['slug' => 'levante', 'name' => 'لوانته'],
                    ['slug' => 'quattroporte', 'name' => 'کواتروپورته'],
                ],
            ],
            45 => [ // Pagani
                'company_slug' => 'pagani',
                'company_name' => 'پاگانی',
                'cars' => [
                    ['slug' => 'huayra', 'name' => 'هوایرا'],
                    ['slug' => 'zonda', 'name' => 'زوندا'],
                ],
            ],
            46 => [ // Volvo
                'company_slug' => 'volvo',
                'company_name' => 'ولوو',
                'cars' => [
                    ['slug' => 'xc60', 'name' => 'ایکس‌سی ۶۰'],
                    ['slug' => 'xc90', 'name' => 'ایکس‌سی ۹۰'],
                    ['slug' => 's60', 'name' => 'اس ۶۰'],
                    ['slug' => 's90', 'name' => 'اس ۹۰'],
                    ['slug' => 'v60', 'name' => 'وی ۶۰'],
                ],
            ],
            47 => [ // Saab
                'company_slug' => 'saab',
                'company_name' => 'ساب',
                'cars' => [
                    ['slug' => '9-3', 'name' => '۹-۳'],
                    ['slug' => '9-5', 'name' => '۹-۵'],
                ],
            ],
            48 => [ // Koenigsegg
                'company_slug' => 'koenigsegg',
                'company_name' => 'کونیگزگ',
                'cars' => [
                    ['slug' => 'agera', 'name' => 'آگرا'],
                    ['slug' => 'jesko', 'name' => 'جسکو'],
                ],
            ],
            49 => [ // Polestar
                'company_slug' => 'polestar',
                'company_name' => 'پولستار',
                'cars' => [
                    ['slug' => 'polestar-1', 'name' => 'پولستار ۱'],
                    ['slug' => 'polestar-2', 'name' => 'پولستار ۲'],
                ],
            ],
            50 => [ // Tata Motors
                'company_slug' => 'tata-motors',
                'company_name' => 'تاتا',
                'cars' => [
                    ['slug' => 'safari', 'name' => 'سافاری'],
                    ['slug' => 'harrier', 'name' => 'هرییر'],
                ],
            ],
            51 => [ // Mahindra
                'company_slug' => 'mahindra',
                'company_name' => 'ماهیندرا',
                'cars' => [
                    ['slug' => 'scorpio', 'name' => 'اسکورپیو'],
                    ['slug' => 'xuv500', 'name' => 'ایکس‌یو‌وی ۵۰۰'],
                ],
            ],
            52 => [ // Maruti Suzuki
                'company_slug' => 'maruti-suzuki',
                'company_name' => 'ماروتی سوزوکی',
                'cars' => [
                    ['slug' => 'alto', 'name' => 'آلتو'],
                    ['slug' => 'wagon-r', 'name' => 'واگن آر'],
                    ['slug' => 'swift', 'name' => 'سوئیفت'],
                ],
            ],
            53 => [ // Chery
                'company_slug' => 'chery',
                'company_name' => 'چری',
                'cars' => [
                    ['slug' => 'tiggo', 'name' => 'تیگو'],
                    ['slug' => 'tiggo-5', 'name' => 'تیگو ۵'],
                    ['slug' => 'tiggo-7', 'name' => 'تیگو ۷'],
                    ['slug' => 'tiggo-8', 'name' => 'تیگو ۸'],
                    ['slug' => 'qq', 'name' => 'کیوکیو'],
                ],
            ],
            54 => [ // JAC
                'company_slug' => 'jac',
                'company_name' => 'جک',
                'cars' => [
                    ['slug' => 'j5', 'name' => 'جی ۵'],
                    ['slug' => 's5', 'name' => 'اس ۵'],
                    ['slug' => 's3', 'name' => 'اس ۳'],
                    ['slug' => 't6', 'name' => 'تی ۶'],
                ],
            ],
            55 => [ // Geely
                'company_slug' => 'geely',
                'company_name' => 'جیلی',
                'cars' => [
                    ['slug' => 'emgrand', 'name' => 'ام‌گرند'],
                    ['slug' => 'coolray', 'name' => 'کول‌ری'],
                    ['slug' => 'azkarra', 'name' => 'آزکارا'],
                ],
            ],
            56 => [ // MG
                'company_slug' => 'mg',
                'company_name' => 'ام‌جی',
                'cars' => [
                    ['slug' => 'mg3', 'name' => 'ام‌جی ۳'],
                    ['slug' => 'mg5', 'name' => 'ام‌جی ۵'],
                    ['slug' => 'mg-zs', 'name' => 'ام‌جی زداس'],
                    ['slug' => 'mg-hs', 'name' => 'ام‌جی اچ‌اس'],
                ],
            ],
            57 => [ // Great Wall
                'company_slug' => 'great-wall',
                'company_name' => 'گریت وال',
                'cars' => [
                    ['slug' => 'h5', 'name' => 'اچ ۵'],
                    ['slug' => 'h6', 'name' => 'اچ ۶'],
                    ['slug' => 'poer', 'name' => 'پوئر'],
                ],
            ],
            58 => [ // Haval
                'company_slug' => 'haval',
                'company_name' => 'هاوال',
                'cars' => [
                    ['slug' => 'h2', 'name' => 'اچ ۲'],
                    ['slug' => 'h6', 'name' => 'اچ ۶'],
                    ['slug' => 'jolion', 'name' => 'جولیون'],
                ],
            ],
            59 => [ // BYD
                'company_slug' => 'byd',
                'company_name' => 'بی‌وای‌دی',
                'cars' => [
                    ['slug' => 'han', 'name' => 'هان'],
                    ['slug' => 'tang', 'name' => 'تانگ'],
                    ['slug' => 'song', 'name' => 'سونگ'],
                ],
            ],
            60 => [ // FAW
                'company_slug' => 'faw',
                'company_name' => 'فاو',
                'cars' => [
                    ['slug' => 'bestune-t77', 'name' => 'بستون تی۷۷'],
                    ['slug' => 'bestune-t99', 'name' => 'بستون تی۹۹'],
                ],
            ],
            61 => [ // Dongfeng
                'company_slug' => 'dongfeng',
                'company_name' => 'دانگ‌فنگ',
                'cars' => [
                    ['slug' => 'a9', 'name' => 'آ۹'],
                    ['slug' => 'sx6', 'name' => 'اس‌ایکس۶'],
                ],
            ],
            62 => [ // Changan
                'company_slug' => 'changan',
                'company_name' => 'چانگان',
                'cars' => [
                    ['slug' => 'cs35', 'name' => 'سی‌اس ۳۵'],
                    ['slug' => 'cs55', 'name' => 'سی‌اس ۵۵'],
                    ['slug' => 'cs75', 'name' => 'سی‌اس ۷۵'],
                    ['slug' => 'uni-t', 'name' => 'یونی-تی'],
                ],
            ],
            63 => [ // GAC
                'company_slug' => 'gac',
                'company_name' => 'گک',
                'cars' => [
                    ['slug' => 'gs3', 'name' => 'جی‌اس ۳'],
                    ['slug' => 'gs4', 'name' => 'جی‌اس ۴'],
                    ['slug' => 'gs8', 'name' => 'جی‌اس ۸'],
                ],
            ],
            64 => [ // BAIC
                'company_slug' => 'baic',
                'company_name' => 'بایک',
                'cars' => [
                    ['slug' => 'x25', 'name' => 'ایکس ۲۵'],
                    ['slug' => 'x35', 'name' => 'ایکس ۳۵'],
                    ['slug' => 'x55', 'name' => 'ایکس ۵۵'],
                ],
            ],
            65 => [ // Lifan
                'company_slug' => 'lifan',
                'company_name' => 'لیفان',
                'cars' => [
                    ['slug' => 'x50', 'name' => 'ایکس ۵۰'],
                    ['slug' => 'x60', 'name' => 'ایکس ۶۰'],
                    ['slug' => 'solano', 'name' => 'سولانو'],
                ],
            ],
            66 => [ // IKCO
                'company_slug' => 'ikco',
                'company_name' => 'ایران خودرو',
                'cars' => [
                    ['slug' => 'dena', 'name' => 'دنا'],
                    ['slug' => 'dena-plus', 'name' => 'دنا پلاس'],
                    ['slug' => 'samand', 'name' => 'سمند'],
                    ['slug' => 'peugeot-405', 'name' => 'پژو ۴۰۵'],
                    ['slug' => 'peugeot-206', 'name' => 'پژو ۲۰۶'],
                    ['slug' => 'peugeot-207', 'name' => 'پژو ۲۰۷'],
                ],
            ],
            67 => [ // Saipa
                'company_slug' => 'saipa',
                'company_name' => 'سایپا',
                'cars' => [
                    ['slug' => 'saina', 'name' => 'ساینا'],
                    ['slug' => 'tiba', 'name' => 'تیبا'],
                    ['slug' => 'tiba-2', 'name' => 'تیبا ۲'],
                    ['slug' => 'quick', 'name' => 'کوییک'],
                    ['slug' => 'kian', 'name' => 'کیان'],
                    ['slug' => 'pride', 'name' => 'پراید'],
                ],
            ],
            68 => [ // Pars Khodro
                'company_slug' => 'pars-khodro',
                'company_name' => 'پارس خودرو',
                'cars' => [
                    ['slug' => 'pars', 'name' => 'پارس'],
                    ['slug' => 'tondar-90', 'name' => 'تندر ۹۰'],
                    ['slug' => 'sabrina', 'name' => 'صبرینا'],
                ],
            ],
            69 => [ // Kerman Motor
                'company_slug' => 'kerman-motor',
                'company_name' => 'کرمان موتور',
                'cars' => [
                    ['slug' => 'verna', 'name' => 'ورنا'],
                    ['slug' => 'xantia', 'name' => 'زانتیا'],
                    ['slug' => 'rexton', 'name' => 'رکستون'],
                ],
            ],
            70 => [ // Bahman Motor
                'company_slug' => 'bahman-motor',
                'company_name' => 'بهمن موتور',
                'cars' => [
                    ['slug' => 'mazda3', 'name' => 'مزدا ۳'],
                    ['slug' => 'mazda6', 'name' => 'مزدا ۶'],
                    ['slug' => 'cx-5', 'name' => 'سی‌ایکس ۵'],
                    ['slug' => 'cx-9', 'name' => 'سی‌ایکس ۹'],
                ],
            ],
            71 => [ // MVM
                'company_slug' => 'mvm',
                'company_name' => 'مدیران خودرو',
                'cars' => [
                    ['slug' => 'x22', 'name' => 'ایکس ۲۲'],
                    ['slug' => 'x33', 'name' => 'ایکس ۳۳'],
                    ['slug' => 'x55', 'name' => 'ایکس ۵۵'],
                    ['slug' => 'x77', 'name' => 'ایکس ۷۷'],
                    ['slug' => 's111', 'name' => 'اس ۱۱۱'],
                ],
            ],
            72 => [ // Farda Motors
                'company_slug' => 'farda-motors',
                'company_name' => 'فردا موتورز',
                'cars' => [
                    ['slug' => 'farda-f4', 'name' => 'فردا اف۴'],
                    ['slug' => 'farda-f5', 'name' => 'فردا اف۵'],
                ],
            ],
            73 => [ // Mammut Khodro
                'company_slug' => 'mammut-khodro',
                'company_name' => 'ماموت خودرو',
                'cars' => [
                    ['slug' => 'mammut-x5', 'name' => 'ماموت ایکس۵'],
                    ['slug' => 'mammut-x6', 'name' => 'ماموت ایکس۶'],
                ],
            ],
        ];

        foreach ($carData as $companyData) {
            $company = CarCompany::where('slug', $companyData['company_slug'])->firstOrFail();
            foreach ($companyData['cars'] as $carData) {
                if (
                    Car::where('slug', $carData['slug'])
                        ->where('car_company_id', '!=', $company->id)
                        ->exists()
                ) {
                    $carData['slug'] = $company->slug.'-'.$carData['slug'];
                }
                $company
                    ->cars()
                    ->updateOrCreate([
                        'slug' => $carData['slug'],
                    ], $carData);
            }
        }
        Storage::disk('public')->makeDirectory('car/cars');

        $cars = Car::whereDoesntHave('images')->get();

        $client = new \GuzzleHttp\Client([
            'curl' => [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            ],
            'verify' => false,
        ]);

        $apiKey = 'x4iavvVgHyPtKFj6dO5J64-Dl4bTYDTmGQpiV2JYkuI';
        /** @var Car $car */
        foreach ($cars as $car) {
            $srcPath = database_path("seeders/data/images/cars/$car->slug");
            $destPath = Storage::disk('public')->path("car/cars/$car->slug");
            $dirExists = file_exists($srcPath);
            $imagesExist = $dirExists && count(scandir($srcPath)) > 2;

            if ($imagesExist) {
                $this->command?->info('Car Has Images: '.$car->slug);
                foreach (scandir($srcPath) as $image) {
                    if ($image != '.' && $image != '..') {
                        if (! file_exists($destPath)) {
                            mkdir("$destPath", 0755, true);
                        }
                        copy($srcPath.'/'.$image, $destPath.'/'.$image);
                        $mimeType = MimeTypes::getDefault()->guessMimeType($srcPath.'/'.$image);
                        $car->images()->create([
                            'path' => 'car/cars/'.$car->slug.'/'.$image,
                            'collection' => 'images',
                            'disk' => 'public',
                            'original_name' => $image,
                            'mime_type' => $mimeType,
                            'size' => filesize($srcPath.'/'.$image),
                        ]);
                    }
                }
            } else {
                $cacheKey = 'update-'.$car->slug;
                $isChecked = Cache::has($cacheKey);
                if ($isChecked) {
                    $lastChecked = Carbon::parse(Cache::get($cacheKey));
                    if ($lastChecked->addDays(14)->isAfter(now())) {
                        $this->command?->info('Skipped Car: '.$car->slug);

                        continue;
                    } else {
                        $this->command?->info('Car is checked but is old: '.$car->slug);
                        Cache::set($cacheKey, now()->toISOString());
                    }
                } else {
                    $this->command?->info('Car not in cache: '.$car->slug);
                    Cache::set($cacheKey, now()->toISOString());
                }
                $proxy = 'socks5://localhost:10808';
                $response = Http::withOptions([
                    'proxy' => $proxy, // HTTP proxy
                    'curl' => [
                        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                    ],
                    'timeout' => 60,
                    'connect_timeout' => 20,
                ])->withoutVerifying()
                    ->get('https://api.unsplash.com/search/photos', [
                        'query' => $car->slug.' car',
                        'client_id' => $apiKey,
                        'per_page' => 1,
                    ]);
                if (! file_exists($srcPath)) {
                    mkdir($srcPath, 0755, true);
                }
                if (! file_exists($destPath)) {
                    mkdir($destPath, 0755, true);
                }
                $this->command?->info('Getting Images for car: '.$car->slug);
                if ($response->successful()) {
                    foreach ($response->json()['results'] as $result) {
                        $image = $result['urls']['regular'] ?? null;
                        if ($image) {
                            $response = Http::withOptions([
                                'proxy' => $proxy,
                                'curl' => [
                                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                                    CURLOPT_TIMEOUT => 60,
                                    CURLOPT_CONNECTTIMEOUT => 30,
                                ],
                                'verify' => false, // If SSL issues
                            ])->withHeaders([
                                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                            ])->get($image);
                            if ($response->successful()) {
                                $imageContent = $response->body();

                                // Generate filename
                                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                                if (! $finfo) {
                                    throw new \Exception("Can't determine mime type of $imageContent");
                                }
                                $mimeType = finfo_buffer($finfo, $imageContent);
                                $extension = $this->mimeToExtension($mimeType);
                                $path = 'cars/'.$car->slug.'/'.basename(parse_url($image, PHP_URL_PATH) ?: '').'.'.$extension;

                                // Save to storage
                                $imgDestPath = Storage::disk('public')->put("car/$path", $imageContent);
                                copy(
                                    Storage::disk('public')->path("car/$path"),
                                    database_path("seeders/data/images/$path")
                                );

                                $car->images()->updateOrCreate([
                                    'original_name' => basename($path),
                                ], [
                                    'collection' => 'images',
                                    'disk' => 'public',
                                    'path' => "car/$path",
                                    'mime_type' => $mimeType,
                                    'size' => filesize(Storage::disk('public')->path("car/$path")),
                                ]);

                                $this->command?->info("✓ Image downloaded: {$path}");
                            } else {
                                $this->command?->error('Failed to download: '.$response->status());
                            }
                        }
                    }
                }
            }
        }
    }

    /** @param string|false $mimeType */
    public function mimeToExtension($mimeType): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'image/bmp' => 'bmp',
            'image/tiff' => 'tiff',
            'image/x-icon' => 'ico',
            'image/avif' => 'avif',
        ];

        return $map[$mimeType] ?? 'jpg';
    }
}
