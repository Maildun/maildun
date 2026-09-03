---
paths:
  - 'app/Models/Subscriber.php,resources/js/pages/audiences/show.tsx,app/Http/Controllers/SubscriberController.php'
---

# Pages Audiences Http Controllers

## Subscriber avatars are DiceBear Micah
Each subscriber gets a stable Micah portrait from `https://api.dicebear.com/10.x/micah/svg?seed={uuid}&backgroundColor=ffe3ea,e3edff,e2f5e9,fdf1d4,efe6ff&borderRadius=50&scale=0.9` via Subscriber::avatar. Pastel Wall backgrounds vary with the seed, `borderRadius=50` makes the SVG a circle, and `scale=0.9` keeps the figure inside that circle. Do not switch subscribers back to critters or Close Up (`scale=1.2`); audience logos stay shape-grid. The subscribers table header checkbox selects or clears every row on the current page (bulk unsubscribe/delete follow from that selection).
