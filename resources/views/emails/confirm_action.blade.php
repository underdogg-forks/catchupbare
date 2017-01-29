<script type="application/ld+json">
    {
        "@context":"http://schema.org",
        "@type":"EmailMessage",
        "description":"Confirm your Invoice Ninja company",
        "action":
        {
            "@type":"ConfirmAction",
            "name":"Confirm company",
            "handler": {
                "@type": "HttpActionHandler",
                "url": "{{{ URL::to("user/confirm/{$user->confirmation_code}") }}}"
            },
            "publisher": {
                "@type": "Organization",
                "name": "Invoice Ninja",
                "url": "{{{ NINJA_WEB_URL }}}"
            }
        }
    }
</script>