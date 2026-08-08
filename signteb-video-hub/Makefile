# SignTeb Video Hub — build and check targets.
#
# Usage:
#   make          فهرست دستورها
#   make check    بررسی کامل (lint + test) — قبل از هر کامیت
#   make zip      ساخت بسته‌ی قابل نصب وردپرس

PLUGIN  := signteb-video-hub
VERSION := $(shell grep -m1 "^ \* Version:" $(PLUGIN).php | awk '{print $$3}')
PHP     ?= php
BUILD   := build
DIST    := $(BUILD)/$(PLUGIN)
ZIP     := $(BUILD)/$(PLUGIN)-$(VERSION).zip

# Files that ship to a production site. Everything else is development-only
# and is deliberately excluded from the installable package.
EXCLUDE := --exclude=build --exclude=tests --exclude=Makefile \
           --exclude=composer.json --exclude=composer.lock \
           --exclude=.git --exclude=.gitignore --exclude=node_modules \
           --exclude=.DS_Store

.DEFAULT_GOAL := help
.PHONY: help lint test check zip clean version

help: ## نمایش این راهنما
	@echo "SignTeb Video Hub $(VERSION)"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2}'

version: ## نمایش نسخه‌ی افزونه
	@echo $(VERSION)

lint: ## بررسی نحوی همه‌ی فایل‌های PHP
	@echo "==> در حال بررسی نحوی…"
	@failed=0; count=0; \
	for file in $$(find . -name '*.php' -not -path './build/*' -not -path './node_modules/*'); do \
		count=$$((count + 1)); \
		if ! $(PHP) -l "$$file" > /dev/null 2>&1; then \
			$(PHP) -l "$$file"; \
			failed=$$((failed + 1)); \
		fi; \
	done; \
	if [ $$failed -gt 0 ]; then \
		echo "✗ $$failed فایل خطای نحوی دارد"; \
		exit 1; \
	fi; \
	echo "✓ $$count فایل PHP بدون خطای نحوی"

test: ## اجرای تست‌های واحد
	@echo "==> در حال اجرای تست‌ها…"
	@$(PHP) tests/smoke-test.php

check: lint test ## بررسی کامل — قبل از کامیت این را بزنید
	@echo ""
	@echo "✓ همه‌ی بررسی‌ها موفق بود."

$(ZIP): lint test
	@echo "==> در حال ساخت بسته…"
	@rm -rf $(DIST)
	@mkdir -p $(DIST)
	@tar -cf - $(EXCLUDE) . | (cd $(DIST) && tar -xf -)
	@cd $(BUILD) && zip -qr $(notdir $(ZIP)) $(PLUGIN)
	@rm -rf $(DIST)
	@echo "✓ بسته ساخته شد: $(ZIP)"
	@echo "  اندازه: $$(du -h $(ZIP) | cut -f1)"
	@echo ""
	@echo "  نصب: پیشخوان وردپرس ← افزونه‌ها ← افزودن ← بارگذاری افزونه"

zip: $(ZIP) ## ساخت بسته‌ی ZIP قابل نصب (پس از lint و test)

clean: ## پاک کردن فایل‌های ساخته‌شده
	@rm -rf $(BUILD)
	@echo "✓ پاک شد."
