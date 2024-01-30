# Symfony Fast Search Table
**Symfony için cache yapılı filtrelemeli tablo listeleme yapısı.**

Bu sistem listelemeyi direk veri tabanından topluca veri çekerek yapmak yerine, daha hızlı listeleme yapmak için; verileri cache dosyasında tutup, sayfa içi soruglar ile listelemeyi en hızlı şekilde yapabilmeyi amaçlamaktadır.

## Kurulum

```shell
composer require netliva/symfony-fast-search-table
```


### Bundle'ı aktifleştir

Ardından, projenizin "app/AppKernel.php" dosyasında paketi kayıtlı paketler listesine ekleyerek etkinleştirin:

```php
<?php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            // ...
            new Netliva\SymfonyFastSearchBundle\NetlivaSymfonyFastSearchBundle(),
        );

        // ...
    }

    // ...
}
```


### Sayfa Yolu Tanımlanması
Sayfada tablo verilerinin yüklenebilmesi için aşağıdaki yol tanımlamasının yapılması gerekmektedir.


```yaml
NetlivaFastTableBundle:
  resource: "@NetlivaSymfonyFastSearchBundle/Controller/"
  type: annotation
  prefix: /fasttable
```
* **prefix:** Ön ek için dilediğiniz bir yol değeri girebilirsiniz.


### Ayarlar Yapılandırması

Oluşturacağınız tabloların nasıl ve ne şekilde oluşturulacağının bilgisini yapılandırın.

```yaml
# Symfony >= 4.0. Create a dedicated netliva_config.yaml in config/packages with:
# Symfony >= 3.3. Add in your app/config/config.yml:

# Netliva Fast Search
netliva_symfony_fast_search:
  cache_path: %kernel.cache_dir%/../shared/fastsearch
  default_limit_per_page: 15
  default_input_class: 'form-control'
  entities: ~

```
* **cache_path:** Ön bellek dosyalarınızın nerede oluşturulacağını tanımlamanızı sağlar.
* **default_limit_per_page:**  Tabloda her seferinde kaç kaydın listeleneceğinin belirlenmesi
* **default_input_class:**  Arama form elemanlarının varsayılan css classlarının tanımlanması 
* **entities:** Önbelleğe alınacak, tabloların yapılandırılması

### Bağımlılıklar
Hızlı  tablolamanın çalışabilmesi için bazı js bağımlılıklarına sahiptir.
Vue ve Axios sisteminizde dahil değilse ilgili linklerini sitenizin temasına ilave edin;

```html 
<script src="https://unpkg.com/vue@3"></script>
<script src="https://unpkg.com/axios/dist/axios.min.js"></script>
```

## Kullanım

**User** adında bir entitimiz olduğunu kabul edelim. Ve bu tablomuzda, ad **(name)**, soyad **(surname)**,
doğum tarihi **(birthday)** ve oluşturma tarihi **(createAt)** verisi tuttuğumuzu varsayıyoruz.
Bu tablodaki verilere göre aşağıdaki konfigrasyonu yapabiliriz.

### Konfigrasyon
Öncelikle önbelleğe alınacak verileri konfirasyon dosyasında tanımlamalıyız. Tanılama **entities** belirteci altında yapılır. 
Her bir tablonuz bunun altında tanımlanır. Key değeri olarak istediğiniz değeri verebilirsiniz. Bu değeri listelemeyi oluştururken kullanacağız.
```yaml
netliva_symfony_fast_search:
  # ...
  entities:
    user_table:
      class: App\DefaultBundle\Entity\Users # Haangi entitiy için veriler önbelleğe alınacak 
      limit_per_page: 15 # Her seferinde yüklenecek veri, tanılanmazsa default_input_class geçerli olur.
      default_sort_field: createAt # varsayılan sıralama hangi field için yapılacak
      default_sorting_direction: desc # varsayılşan sıralama yönü  
      where: # Önbelleğe alınacak ve listelenecek verileri kısıtlamak için. Bu kısıtlamalar sonrası kısıtlanan veriler hiç önbelleğe alınmaz.
        - { field: createAt, expr: gte, value: -2 years, valueType: date } # son 2 yıla ait verileri önbelleğe alır
      fields: # ön belleğe alınacak verilerin listesi 
        name: { title: 'Adı' }  # entitiy'deki field isimleri key olarak eklenerek liste oluşturulur.
        surname: { title: 'Soyadı' }  
        birthday: { title: 'Doğum Tarihi' } # tüm tarihler ISO 8601 tarih formati ile önbelleğe kaydedilir (2004-02-12T15:19:21+00:00 gibi)
        createAt: { title: 'Oluşturma Tarihi' }
        age: { title: 'Yaşı' } # entitiy'de olmayan veriyi ön belleğe kaydetmek için maniplasyon kullnacağız.
        # ... 
      filters: # Listeleme tablosu üzerindeki filtreleme alanının oluşturulması
        search_box: { type: 'text', title: 'Ara', fields: [name, surname] }
        create_range: { type: 'date_range', title: 'Oluşturma', fields: [createAt] }

      cache_clear: # ilişkili başka entitiy üzerinden bir veri bu tablo için cachlenmiş ise, o entitiyde değişiklik yapıldığında bağlı bu tablodaki veririnin cache'inin temizlenmesi için yapılan tanımlama  
        Crm\DefaultBundle\Entity\OtherEntity: { reverse_fields: [ user ] } # reverse_fields ile diğer tablodan user tablosuna hangi field tanımıyla ulaşıldığı bilgisi tanımlanır. Böylece diğer tablodaki bir veri değiştirildiğinde, bağlantılı user tablolarının cache'i de düzenlenir

    other_table: # listelenencek diğer tablolar için tanılama yapılmaya devam edilir.
      # ...  
```
### Listeleme


```twig
{% import _self as funcs %}

{% set tableColumns = [
    {'title': 'Adı', 'releated_field': 'name'},
    {'title': 'Soyadı', 'releated_field': 'surname'},
    {'title': 'Yaşı', 'releated_field': 'age'},
    {'title': 'Oluşturma', 'releated_field': 'createAt'},
    {'title': 'İşlemler'},
] %}

<div id="bina_bilgileri_table">
    {{ get_fast_search_table('all_bids', {
        'table_class'                    : 'table table-striped table-hover table-bordered font-size-12',
        'record_variable_name'           : 'user_data',
        'table_tbody_cells_vue_template' : funcs.tBodyCells(),
        'table_columns' : tableColumns,
        'vue_variables' : {
            vueVarName: twigVarName,
        }
    }) }}
</div>

{% macro tBodyCells() %}
	<td>[[ user_data.name ]]</td>
	<td>[[ user_data.surname ]]</td>
	<td>[[ user_data.age ]] Yaşında</td>
	<td>[[ user_data.createAtText ]]</td>
	<td>
        <a class="btn btn-xs btn-info"
           target="_blank"
           :href="'{{ path('users_show', { 'id': '__ID__' }) }}'.replace('__ID__', user_data.id)" 
        >
            Görüntüle
        </a>
	</td>
{% endmacro %}
```
**table_tbody_cells_vue_template** değişkeni içinde bir vue template göndermek gerekiyor. Bunun için twig makro kullanıyoruz.
twig parentezlerinin **{{ }}** vue parantezleri ile karışmaması için; vue parantezlerini **[[ ]]** şeklinde köşeli parantez olarak 
kullanmamız gerekiyor.

**record_variable_name** her bir listelenecek kaydın vue template içinde hangi değişken tanımıyla alacağınızı tanımlayacağınız bölüm.

**vue_variables** ile vue template içinde kullanmak isteyeceğiniz twig değişkenlerini gönderebilirsiniz.

**js_variables** ile vue template içinde kullanmak isteyeceğiniz javascript değişkenlerini gönderebilirsiniz.

**js_methods** ile vue template içinde kullanmak isteyeceğiniz javascript fonksiyonlarını gönderebilirsiniz.

```
//...
'js_methods' : {
	moment: '(date) => window.moment(date)',
}
//...
```
**components** ile vue template içinde import etmek isteyeceğiniz componentleri gönderebilirsiniz.

```
//...
'js_methods' : {
	'ComponentAdi': asset('vue_components/component_dosyasi.js'),
}
//...
```



### Veri Manipülasyonu

Veriyi cache'e kadetmeden önce ve listeleme işleminden hemen önce kullanabileceğiniz iki tane event listener mevcut;
Bu listenerları aşağıdaki örnekteki gibi tanımlayabilirsin;

```yaml
services:
  #...
  user_fasttable_subscriber:
    class: App\DefaultBundle\EventListener\FastTableSubscriber
    tags:
      - { name: kernel.event_subscriber }
```

```php
<?php
namespace App\DefaultBundle\EventListener\FastTableSubscriber;

use App\DefaultBundle\Entity\Users;
use Netliva\SymfonyFastSearchBundle\Events\BeforeViewEvent;
use Netliva\SymfonyFastSearchBundle\Events\NetlivaFastSearchEvents;
use Netliva\SymfonyFastSearchBundle\Events\PrepareRecordEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FastTableSubscriber implements EventSubscriberInterface
{

	public static function getSubscribedEvents ()
	{
		return [
            NetlivaFastSearchEvents::PREPARE_RECORD => 'prepareRecord',
            NetlivaFastSearchEvents::BEFORE_VIEW => 'beforeView',
		];
	}

	public function prepareRecord (PrepareRecordEvent $event)
	{
        $entity    = $event->getEntity(); // kaydedilecek ilgili entity
        $fKey      = $event->getFKey(); // kaydedilecek field ley
        $entityKey = $event->getEntityKey(); // kayıt yapılacak tablo tanımı
        $value     = $event->getValue(); // kaydedilecek veri

        switch ($entityKey)
        {
            case 'user_table': // hangi tablo tanımlaması için işlem yapılacağının seçilmesi
                if ($entity instanceof Users) // gelen verinin bu tabloaya ait entitiy'e mi ait olduğunun kontrolü
                {
                    switch ($fKey)
                    {
                        case 'age' :
                            $value = $entity->getBirthday() ? $entity->getBirthday()->diff(new DateTime())->y : '---';
                            break;
                    }
                }
            break;
        }

        // değiştirilen veriyi kaydediyoruz
        $event->setValue($value);
	}


    public function beforeView (BeforeViewEvent $event)
    {
        // gerektiğinde filtre verileri gibi post edilen verileri alabilirsiniz
        $postedData = $event->getRequests();
        
        // listelenecek verileri döndürüyoruz
        foreach ($event->getRecords() as $key => $record)
        {
            switch ($event->getEntityKey())
            {
                // düzenleme yapacağımız tabloyu belirliyoruz
                case 'user_table':
                    // gelen oluşturma tarihi verisini, okunabilir tarih formatına çevirerek yeni bir değişken ile vue template'e gönderiyoruz
                    $record['createAtText'] = $record['createAt']?(new \DateTime($record['createAt']))->format('d.m.Y H:i'):null;
                    $event->updateRecord($key, $record);
                    break;
            }
        }
    }
}

```



