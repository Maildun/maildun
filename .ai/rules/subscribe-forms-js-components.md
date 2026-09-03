---
paths:
  - '{app/Models/SubscribeForm.php,app/Http/Controllers/*SubscribeForm*.php,app/Http/Requests/SaveSubscribeFormRequest.php,resources/js/pages/subscribe-forms/**,resources/js/components/subscribe-form-view.tsx}'
---

# Subscribe Forms Js Components

## Subscribe forms own their brand theme
Each subscribe form persists brand_color, brand_font, and brand_input_style and both editor preview and public rendering read those form values. Workspace theme values are only defaults when a form is created; later workspace theme changes must not alter existing forms.
