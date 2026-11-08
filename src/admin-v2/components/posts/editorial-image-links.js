function link(value) {
  return value && typeof value === 'object' && !Array.isArray(value) ? value : {};
}

function captionData(props) {
  try {
    return link(JSON.parse(props.captionLinkJson || '{}'));
  } catch {
    return {};
  }
}

export function imageLinkFromProps(props) {
  return {
    href: props.href || '',
    title: props.title || '',
    target: props.target || '',
    rel: props.rel || '',
    variant: props.variant || '',
    id: props.htmlId || '',
    aria_label: props.ariaLabel || '',
  };
}

export function imageLinkState(block) {
  const image = link(block.link);
  const caption = link(block.caption_link);
  return {
    imageLinked: image.href ? '1' : '0',
    captionIndependent:
      caption.href && JSON.stringify(image) !== JSON.stringify(caption) ? '1' : '0',
    captionLinkJson: JSON.stringify(caption),
  };
}

export function imageLinkValues(props, image) {
  return {
    link: props.imageLinked === '0' ? {} : image,
    caption_link:
      props.captionLinked === '1'
        ? props.captionIndependent === '1'
          ? captionData(props)
          : image
        : {},
  };
}

export function captionLinkProps(props) {
  const data = captionData(props);
  return { ...data, htmlId: data.id || '', ariaLabel: data.aria_label || '' };
}

export function patchCaptionLink(props, patch) {
  const data = { ...captionData(props) };
  for (const [key, value] of Object.entries(patch))
    data[key === 'htmlId' ? 'id' : key === 'ariaLabel' ? 'aria_label' : key] = value;
  return { captionLinkJson: JSON.stringify(data) };
}
