import postcssRtlcss from 'postcss-rtlcss'

// VitePress styles its layout for left-to-right pages; this mirrors it for
// the Arabic pages, as the VitePress docs recommend for right-to-left sites.
export default {
  plugins: [
    postcssRtlcss({
      ltrPrefix: ':where([dir="ltr"])',
      rtlPrefix: ':where([dir="rtl"])',
    }),
  ],
}
