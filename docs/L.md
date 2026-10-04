# YouTube: nahrání až po schválení

`main` (4. 10. 2026). Roman: videa se nahrávala na YouTube jako soukromá hned po dokončení tisku a čekala tam na
schválení. YouTube ale počítá každé nahrání do denního limitu kanálu (mladý kanál „Matplace – 3D“ dostal
`uploadLimitExceeded` už po dvou videích) a zamítnutá videa ho spotřebovávala zbytečně.

## Nový tok

```
pending (čeká na schválení, na YouTube nic) → queued (schváleno) → uploading → published
uploaded = na YouTube soukromé (YouTube ho nechal soukromé – neauditovaný API projekt – nebo starší kopie) → published
kdykoli → rejected (admin) | withdrawn (zákazník odvolal souhlas; kopie na YouTube se smaže)
```

- `FarmVideos::queueFor` (konec `BuildFarmTimelapse`, pozdější souhlas zákazníka, „Nahrát na YouTube“ v adminu) jen
  založí `pending` a pošle adminovi mail „Video ke schválení“.
- Admin v `/admin/youtube` upraví název a popis a klikne **Nahrát a zveřejnit na YouTube** → `FarmVideos::publish`
  zapíše `approved_at`, `decided_by/at`, stav `queued` a pustí `UploadFarmVideo`; po nahrání se video hned zveřejní.
  Když YouTube odmítne nahrání kvůli limitu, zůstane `queued` se schválením a zkusí se znovu za 6 h (text u videa říká
  kdy); po úspěšném nahrání se zveřejní samo. Když YouTube nechá video soukromé (audit API projektu), zůstane `uploaded`
  s chybou a admin ho zveřejní znovu tlačítkem, nebo ručně ve Studiu.
- **Zamítnout** jde kdykoli; co nebylo na YouTube, se tam nikdy nedostane.
- **Nahradit novou verzí** (po přestavění videa) smaže kopii na YouTube a video jde znovu do `pending`.
- **Nahrát znovu** u schváleného videa znovu pustí nahrání; u neschváleného (po migraci) ho vrátí do `pending`.
- Migrace `2026_10_11_100000_farm_videos_pending`: sloupec `approved_at`; co čekalo ve frontě bez schválení, je teď
  `pending`. Naplánované `UploadFarmVideo` joby těchto videí skončí bez akce (stav není `queued`).

## Plánované zveřejnění (doplněno)

Zhlédnutí Shorts rozhodují první hodiny po zveřejnění a kanál, který vysype tři videa za dopoledne, si konkuruje
sám se sebou. Proto schválené video nejde ven hned, ale v **nejbližším volném termínu**: `youtube.publish_times`
(výchozí `18:00`, env `YOUTUBE_PUBLISH_TIMES=18:00,11:00`), nejvýš `youtube.max_per_day` videí denně (výchozí 1),
čas kanálu Europe/Prague. Termín drží `farm_videos.scheduled_at`; video se nahraje hned po schválení jako soukromé
s `status.publishAt` a **YouTube ho v ten čas zveřejní sám** (stav `scheduled`). Hodinový `youtube:stats` to pak
zapíše jako `published` s časem termínu. Když schválení čeká na limit nahrávání a termín mezitím propadne, při
nahrání se vezme další volný. Admin může u každého videa zvolit „hned“ a u naplánovaného kliknout „Zveřejnit hned“.
`FarmVideos::nextSlot` počítá do dne i videa zveřejněná „hned“, takže se schválení sama rozprostřou po dnech.

## Fotka z foto-boxu na konci videa

Video zakázky končí fotkou hotového kusu; kterou, říká `TestPhotos::finishIndex`: přednostně boční pohled (zleva,
zprava), jinak z mobilu, jinak shora, vždy nejnovější. Foto-box ji označí „do videa“, fotky jdou mazat (✕) a po
smazání i vyfocení se video přestaví (`AddFinishPhotoToVideo`). Karta videa v `/admin/youtube` ukazuje, která fotka
na konci bude (náhled), nebo odkaz do foto-boxu, když žádná není.

## Testy

`YouTubeVideosTest`: souhlas → `pending` bez požadavku na Google; schválení = nahrání soukromě + zveřejnění v jednom
kroku s upraveným názvem; nahrazení → `pending`; limit YouTube drží schválení a plánuje další pokus; „nahrát znovu“
podle schválení; neauditovaný projekt nechá `uploaded` s chybou. `FarmTestPhotosTest` beze změny.
