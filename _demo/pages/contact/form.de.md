---
title: 'Nachricht schreiben'
menu: Kontakt
eyebrow: Kontakt
lead: 'Schreiben Sie eine kurze Nachricht. Die Antwort kommt per E-Mail.'
cache_enable: false
form:
    name: kontakt
    refresh_prevention: true
    fields:
        name:
            label: Name
            type: text
            autocomplete: name
            validate:
                required: true
        email:
            label: 'E-Mail-Adresse'
            type: email
            autocomplete: email
            validate:
                required: true
        message:
            label: Nachricht
            type: textarea
            rows: 8
            validate:
                required: true
        honeypot:
            type: honeypot
    buttons:
        submit:
            type: submit
            value: 'Nachricht senden'
    process:
        save:
            fileprefix: nachricht-
            dateformat: Ymd-His-u
            extension: txt
            body: "{% include 'forms/data.txt.twig' %}"
        email:
            from: '{{ config.plugins.email.from ?: config.site.author.email }}'
            to: '{{ config.plugins.email.to ?: config.site.author.email }}'
            reply_to: '{{ form.value.email|e }}'
            subject: 'Demo-Kontaktformular: Nachricht von {{ form.value.name|e }}'
            body: "{% include 'forms/data.html.twig' %}"
        redirect: /contact/thanks
---

Demo-Formular. Ihre Angaben werden nur zur Beantwortung verwendet.
