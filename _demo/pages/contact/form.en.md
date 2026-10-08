---
title: 'Write a message'
menu: Contact
eyebrow: Contact
lead: 'Send a short message. You will receive a reply by email.'
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
            label: 'Email address'
            type: email
            autocomplete: email
            validate:
                required: true
        message:
            label: Message
            type: textarea
            rows: 8
            validate:
                required: true
        honeypot:
            type: honeypot
    buttons:
        submit:
            type: submit
            value: 'Send message'
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
        redirect: /en/contact/thanks
---

Demo form. Your details are only used to reply.
